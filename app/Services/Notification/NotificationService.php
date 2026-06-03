<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\AppNotification;
use App\Models\Entity;
use App\Models\EntityContact;
use App\Models\Federation;
use App\Models\MailLog;
use App\Models\NotificationType;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\Mail\MailTemplateService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use App\Mail\FederationMail;

class NotificationService
{
    public function dispatch(
        string $type,
        array $data,
        ?Entity $entity = null,
        ?Federation $federation = null
    ): void {
        $notifType = NotificationType::find($type);

        if ($notifType === null || !$notifType->is_active) {
            return;
        }

        $recipients = $this->resolveRecipients($notifType, $entity, $federation);

        $title = $notifType->label;
        $body  = $data['body'] ?? $this->buildBody($notifType, $data);

        $actionUrl   = $data['action_url'] ?? null;
        $subjectType = null;
        $subjectId   = null;

        if ($entity !== null) {
            $subjectType = 'entity';
            $subjectId   = $entity->id;
        } elseif ($federation !== null) {
            $subjectType = 'federation';
            $subjectId   = $federation->id;
        }

        foreach ($recipients as $user) {
            $pref = UserNotificationPreference::where('user_id', $user->id)
                ->where('notification_type', $type)
                ->first();

            $viaUi    = $pref !== null ? $pref->via_ui    : $notifType->default_via_ui;
            $viaEmail = $pref !== null ? $pref->via_email : $notifType->notify_email;

            if ($viaUi) {
                AppNotification::create([
                    'user_id'      => $user->id,
                    'type'         => $type,
                    'title'        => $title,
                    'body'         => $body,
                    'subject_type' => $subjectType,
                    'subject_id'   => $subjectId,
                    'action_url'   => $actionUrl,
                ]);
            }

            if ($viaEmail && $notifType->mail_template_id !== null) {
                $this->sendEmailToUser($user, $notifType, $data, $entity, $federation);
            }
        }

        $this->notifyExternalContacts($notifType, $data, $entity, $federation);
    }

    /** @return Collection<int, User> */
    private function resolveRecipients(
        NotificationType $notifType,
        ?Entity $entity,
        ?Federation $federation
    ): Collection {
        $users = collect();

        if ($notifType->notify_submitter && $entity !== null) {
            $owners = $entity->entityManagers()
                ->where('role', 'owner')
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter();

            $users = $users->merge($owners);
        }

        if ($notifType->notify_federation_managers) {
            $fed = $federation;

            if ($fed === null && $entity !== null) {
                $fed = $entity->federations()
                    ->wherePivot('status', 'active')
                    ->first();
            }

            if ($fed !== null) {
                $managers = $fed->managers()->get();
                $users    = $users->merge($managers);
            }
        }

        if ($notifType->notify_admins) {
            $admins = User::role('Admin')->get();
            $users  = $users->merge($admins);
        }

        return $users->unique('id')->values();
    }

    private function buildBody(NotificationType $notifType, array $data): string
    {
        $parts = match ($notifType->id) {
            'user_registered' => $this->buildUserRegisteredBody($data),
            default           => [$notifType->description],
        };

        if (isset($data['entity_name'])) {
            $parts[] = 'Entity: ' . $data['entity_name'];
        }

        if (isset($data['federation_name'])) {
            $parts[] = 'Federation: ' . $data['federation_name'];
        }

        if (isset($data['reason']) && $data['reason'] !== '') {
            $parts[] = 'Reason: ' . $data['reason'];
        }

        return implode('. ', array_filter($parts));
    }

    private function buildUserRegisteredBody(array $data): array
    {
        $name  = $data['user_name']  ?? null;
        $email = $data['user_email'] ?? null;

        if ($name && $email) {
            return ["New user {$name} ({$email}) has registered and is awaiting role assignment."];
        }

        if ($name) {
            return ["New user {$name} has registered and is awaiting role assignment."];
        }

        return ['A new user has registered and is awaiting role assignment.'];
    }

    private function sendEmailToUser(
        User $user,
        NotificationType $notifType,
        array $data,
        ?Entity $entity,
        ?Federation $federation
    ): void {
        if ($notifType->mailTemplate === null) {
            return;
        }

        // Resolve federation for template lookup: explicit > entity's active federation
        $fed = $federation;
        if ($fed === null && $entity !== null) {
            $fed = $entity->federations()->wherePivot('status', 'active')->first()
                ?? $entity->federations()->orderByPivot('created_at', 'desc')->first();
        }

        $mailService = app(MailTemplateService::class);

        $template = $mailService->resolveTemplate(
            $notifType->mailTemplate->group,
            $notifType->mailTemplate->lang,
            $fed
        ) ?? $notifType->mailTemplate;

        $rendered = $mailService->render($template, [
            'entity'     => $entity,
            'federation' => $fed,
        ]);

        try {
            Mail::to($user->email)->send(new FederationMail($rendered['subject'], $rendered['body']));

            MailLog::create([
                'federation_id' => $fed?->id,
                'entity_id'     => $entity?->id,
                'sent_by'       => null,
                'to_email'      => $user->email,
                'to_name'       => $user->name,
                'contact_type'  => null,
                'subject'       => $rendered['subject'],
                'body'          => $rendered['body'],
                'status'        => 'sent',
                'sent_at'       => now(),
            ]);
        } catch (\Throwable) {
            // Non-fatal — notification row is already written
        }
    }

    private function notifyExternalContacts(
        NotificationType $notifType,
        array $data,
        ?Entity $entity,
        ?Federation $federation
    ): void {
        if ($entity === null) {
            return;
        }

        if (! $notifType->notify_entity_technical && ! $notifType->notify_entity_admin) {
            return;
        }

        $types = [];
        if ($notifType->notify_entity_technical) $types[] = 'technical';
        if ($notifType->notify_entity_admin)     $types[] = 'administrative';

        $contacts = $entity->contacts()->whereIn('type', $types)->get();

        $fed = $federation ?? $entity->federations()->wherePivot('status', 'active')->first();

        $mailService = app(MailTemplateService::class);

        foreach ($contacts as $contact) {
            /** @var EntityContact $contact */
            if ($notifType->mailTemplate !== null) {
                $template = $mailService->resolveTemplate(
                    $notifType->mailTemplate->group,
                    $notifType->mailTemplate->lang,
                    $fed
                ) ?? $notifType->mailTemplate;

                $rendered = $mailService->render($template, [
                    'entity'     => $entity,
                    'federation' => $fed,
                    'contact'    => $contact,
                ]);

                $subject = $rendered['subject'];
                $body    = $rendered['body'];
            } else {
                $subject = $notifType->label;
                $body    = $this->buildBody($notifType, $data);
            }

            try {
                Mail::to($contact->email)->send(new FederationMail($subject, $body));

                MailLog::create([
                    'federation_id' => $fed?->id,
                    'entity_id'     => $entity->id,
                    'sent_by'       => null,
                    'to_email'      => $contact->email,
                    'to_name'       => trim(($contact->given_name ?? '') . ' ' . ($contact->sur_name ?? '')) ?: null,
                    'contact_type'  => $contact->type,
                    'subject'       => $subject,
                    'body'          => $body,
                    'status'        => 'sent',
                    'sent_at'       => now(),
                ]);
            } catch (\Throwable) {
                // Non-fatal
            }
        }
    }
}
