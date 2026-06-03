<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Mail\FederationMail;
use App\Models\Entity;
use App\Models\Federation;
use App\Models\MailLog;
use App\Models\MailTemplate;
use App\Models\User;
use App\Models\EntityContact;
use App\Models\FederationContact;
use Illuminate\Support\Facades\Mail;
use App\Models\SystemPreference;
use Throwable;

class MailTemplateService
{
    /**
     * Render a mail template by substituting all [[placeholder]] values.
     *
     * Expected $data keys (all optional — missing keys produce empty strings):
     *   'entity'           => ?Entity       — provides [[entity_name]], [[entity_id]], [[entity_type]]
     *   'contact'          => ?EntityContact — provides [[contact_name]], [[contact_email]], [[contact_type]]
     *   'federation'       => ?Federation   — provides [[federation_name]]
     *   'cert_expiry_date' => string        — provides [[cert_expiry_date]]
     *   'cert_subject'     => string        — provides [[cert_subject]]
     *
     * [[app_name]], [[app_url]], [[mail_signature]], [[registration_authority]] are resolved
     * automatically from SystemPreference / config — no $data key needed.
     *
     * @return array{subject: string, body: string}
     */
    public function render(MailTemplate $template, array $data): array
    {
        $subject = $template->subject;
        $body    = $template->body;

        $replacements = [
            '[[federation_name]]'        => $data['federation']?->name ?? '',
            '[[entity_name]]'            => $data['entity']?->getDisplayName() ?? '',
            '[[entity_id]]'              => $data['entity']?->entity_id ?? '',
            '[[entity_type]]'            => strtoupper($data['entity']?->type ?? ''),
            '[[contact_name]]'           => trim(($data['contact']?->given_name ?? '') . ' ' . ($data['contact']?->sur_name ?? '')),
            '[[contact_email]]'          => $data['contact']?->email ?? '',
            '[[contact_type]]'           => $data['contact']?->type ?? '',
            '[[registration_authority]]' => config('federation.registration_authority', ''),
            '[[cert_expiry_date]]'       => $data['cert_expiry_date'] ?? '',
            '[[cert_subject]]'           => $data['cert_subject'] ?? '',
            '[[validation_errors]]'      => $this->formatValidationErrors($data['errors'] ?? [], $data['warnings'] ?? []),
            '[[app_name]]'               => SystemPreference::get('app_name', config('app.name', '')),
            '[[app_url]]'                => SystemPreference::get('app_url', config('app.url', '')),
            '[[mail_signature]]'         => SystemPreference::get('mail_signature', ''),
        ];

        foreach ($replacements as $placeholder => $value) {
            $subject = str_replace($placeholder, (string) $value, $subject);
            $body    = str_replace($placeholder, (string) $value, $body);
        }

        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Resolve the best available template for a given group/lang, preferring
     * a federation-specific override over the system default.
     */
    public function resolveTemplate(string $group, string $lang, ?Federation $federation = null): ?MailTemplate
    {
        if ($federation !== null) {
            $override = MailTemplate::where('federation_id', $federation->id)
                ->where('group', $group)
                ->where('lang', $lang)
                ->where('is_active', true)
                ->first();

            if ($override !== null) {
                return $override;
            }
        }

        return MailTemplate::whereNull('federation_id')
            ->where('group', $group)
            ->where('lang', $lang)
            ->where('is_active', true)
            ->first();
    }

    /** @param string[] $errors @param string[] $warnings */
    private function formatValidationErrors(array $errors, array $warnings): string
    {
        $lines = [];

        if (! empty($errors)) {
            $lines[] = 'Errors:';
            foreach ($errors as $msg) {
                $lines[] = '  - ' . $msg;
            }
        }

        if (! empty($warnings)) {
            if (! empty($errors)) {
                $lines[] = '';
            }
            $lines[] = 'Warnings:';
            foreach ($warnings as $msg) {
                $lines[] = '  - ' . $msg;
            }
        }

        return implode("\n", $lines);
    }

    public function sendToEntity(
        Entity $entity,
        string $subject,
        string $body,
        array $contactTypes = ['technical'],
        ?Federation $federation = null,
        ?User $sentBy = null
    ): array {
        $sent    = 0;
        $failed  = 0;
        $skipped = 0;

        $contacts = $entity->contacts()
            ->whereIn('type', $contactTypes)
            ->get();

        if ($contacts->isEmpty()) {
            $skipped++;
            return compact('sent', 'failed', 'skipped');
        }

        foreach ($contacts as $contact) {
            $logEntry = MailLog::create([
                'federation_id' => $federation?->id,
                'entity_id'     => $entity->id,
                'sent_by'       => $sentBy?->id,
                'to_email'      => $contact->email,
                'to_name'       => trim(($contact->given_name ?? '') . ' ' . ($contact->sur_name ?? '')) ?: null,
                'contact_type'  => $contact->type,
                'subject'       => $subject,
                'body'          => $body,
                'status'        => 'pending',
            ]);

            try {
                Mail::to($contact->email)->send(new FederationMail($subject, $body));

                $logEntry->update([
                    'status'  => 'sent',
                    'sent_at' => now(),
                ]);
                $sent++;
            } catch (Throwable $e) {
                $logEntry->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return compact('sent', 'failed', 'skipped');
    }

    public function sendToFederation(
        Federation $federation,
        string $subject,
        string $body,
        string $entityType = 'all',
        array $contactTypes = ['technical'],
        ?User $sentBy = null
    ): array {
        $query = $federation->entities()->wherePivot('status', 'active');

        if ($entityType !== 'all') {
            $query->where('type', $entityType);
        }

        $entities = $query->with('contacts')->get();

        $totalSent    = 0;
        $totalFailed  = 0;
        $totalSkipped = 0;

        foreach ($entities as $entity) {
            $result = $this->sendToEntity(
                $entity,
                $subject,
                $body,
                $contactTypes,
                $federation,
                $sentBy
            );

            $totalSent    += $result['sent'];
            $totalFailed  += $result['failed'];
            $totalSkipped += $result['skipped'];
        }

        return [
            'sent'    => $totalSent,
            'failed'  => $totalFailed,
            'skipped' => $totalSkipped,
        ];
    }

    /**
     * Send a plain subject/body to the federation's own contacts
     * (the people who manage/represent the federation itself).
     *
     * @param  array<string>  $contactTypes  e.g. ['technical','security']
     */
    public function sendToFederationContacts(
        Federation $federation,
        string $subject,
        string $body,
        array $contactTypes = ['technical'],
        ?User $sentBy = null
    ): array {
        $sent    = 0;
        $failed  = 0;
        $skipped = 0;

        $contacts = $federation->contacts()
            ->whereIn('type', $contactTypes)
            ->get();

        if ($contacts->isEmpty()) {
            $skipped++;
            return compact('sent', 'failed', 'skipped');
        }

        foreach ($contacts as $contact) {
            /** @var FederationContact $contact */
            $logEntry = MailLog::create([
                'federation_id' => $federation->id,
                'entity_id'     => null,
                'sent_by'       => $sentBy?->id,
                'to_email'      => $contact->email,
                'to_name'       => trim(($contact->given_name ?? '') . ' ' . ($contact->sur_name ?? '')) ?: null,
                'contact_type'  => $contact->type,
                'subject'       => $subject,
                'body'          => $body,
                'status'        => 'pending',
            ]);

            try {
                Mail::to($contact->email)->send(new FederationMail($subject, $body));

                $logEntry->update([
                    'status'  => 'sent',
                    'sent_at' => now(),
                ]);
                $sent++;
            } catch (Throwable $e) {
                $logEntry->update([
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        return compact('sent', 'failed', 'skipped');
    }
}
