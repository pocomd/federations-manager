<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\Federation;
use App\Services\Mail\MailTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ComplianceNotifyController extends Controller
{
    public function __construct(private readonly MailTemplateService $mailer) {}

    /**
     * Return a rendered preview of the compliance_failure template for one entity.
     * Uses the entity's latest persisted validation result.
     */
    public function previewEntity(Request $request, Entity $entity): JsonResponse
    {
        Gate::authorize('update', $entity);

        $latestResult = $entity->currentValidation;

        $errors   = $latestResult?->errors   ?? [];
        $warnings = $latestResult?->warnings ?? [];

        // errors in DB are full check objects; extract the message string
        $errorMessages   = array_map(fn($c) => is_array($c) ? ("[{$c['id']}] " . $c['message']) : $c, $errors);
        $warningMessages = array_map(fn($c) => is_array($c) ? ("[{$c['id']}] " . $c['message']) : $c, $warnings);

        $federation = $entity->federations()->wherePivot('status', 'active')->first();
        $contact    = $entity->contacts()->where('type', 'technical')->first();

        $template = $this->mailer->resolveTemplate('compliance_failure', 'en', $federation ?? null);

        if ($template === null) {
            return response()->json(['error' => 'No active compliance_failure template found.'], 404);
        }

        $rendered = $this->mailer->render($template, [
            'entity'     => $entity,
            'federation' => $federation,
            'contact'    => $contact,
            'errors'     => $errorMessages,
            'warnings'   => $warningMessages,
        ]);

        return response()->json($rendered);
    }

    /**
     * Send a compliance notification to a single entity's technical contacts.
     */
    public function sendEntity(Request $request, Entity $entity): RedirectResponse
    {
        Gate::authorize('update', $entity);

        $latestResult = $entity->currentValidation;

        if ($latestResult === null || ($latestResult->passed && empty($latestResult->warnings))) {
            return back()->with('info', 'No compliance failures on record for this entity.');
        }

        $errors   = $latestResult->errors   ?? [];
        $warnings = $latestResult->warnings ?? [];

        $errorMessages   = array_map(fn($c) => is_array($c) ? ("[{$c['id']}] " . $c['message']) : $c, $errors);
        $warningMessages = array_map(fn($c) => is_array($c) ? ("[{$c['id']}] " . $c['message']) : $c, $warnings);

        $federation = $entity->federations()->wherePivot('status', 'active')->first();
        $contact    = $entity->contacts()->where('type', 'technical')->first();

        $template = $this->mailer->resolveTemplate('compliance_failure', 'en', $federation ?? null);

        if ($template === null) {
            return back()->with('error', 'No active compliance_failure template found.');
        }

        $rendered = $this->mailer->render($template, [
            'entity'     => $entity,
            'federation' => $federation,
            'contact'    => $contact,
            'errors'     => $errorMessages,
            'warnings'   => $warningMessages,
        ]);

        $result = $this->mailer->sendToEntity(
            $entity,
            $rendered['subject'],
            $rendered['body'],
            ['technical'],
            $federation ?? null,
            Auth::user(),
        );

        if ($result['skipped'] > 0) {
            return back()->with('warning', 'No technical contacts found for this entity — notification not sent.');
        }

        return back()->with('success', "Compliance notification sent ({$result['sent']} sent, {$result['failed']} failed).");
    }

    /**
     * Send compliance notifications to selected entities in a federation.
     * Request body: entity_ids[] — array of entity UUIDs to notify.
     */
    public function sendFederation(Request $request, Federation $federation): RedirectResponse
    {
        Gate::authorize('update', $federation);

        $request->validate([
            'entity_ids'   => ['required', 'array', 'min:1'],
            'entity_ids.*' => ['uuid'],
        ]);

        $entityIds = $request->input('entity_ids');

        $entities = $federation->entities()
            ->wherePivot('status', 'active')
            ->whereIn('entities.id', $entityIds)
            ->with(['contacts', 'federations', 'currentValidation'])
            ->get();

        if ($entities->isEmpty()) {
            return redirect()->route('federations.show', $federation)
                ->with('warning', 'None of the selected entities are active members of this federation.')
                ->withFragment('tab-rules');
        }

        $totalSent    = 0;
        $totalFailed  = 0;
        $totalSkipped = 0;

        foreach ($entities as $entity) {
            $latestResult = $entity->currentValidation;

            if ($latestResult === null) {
                $totalSkipped++;
                continue;
            }

            $errors   = $latestResult->errors   ?? [];
            $warnings = $latestResult->warnings ?? [];

            $errorMessages   = array_map(fn($c) => is_array($c) ? ("[{$c['id']}] " . $c['message']) : $c, $errors);
            $warningMessages = array_map(fn($c) => is_array($c) ? ("[{$c['id']}] " . $c['message']) : $c, $warnings);

            $contact  = $entity->contacts->where('type', 'technical')->first();
            $template = $this->mailer->resolveTemplate('compliance_failure', 'en', $federation);

            if ($template === null) {
                $totalSkipped++;
                continue;
            }

            $rendered = $this->mailer->render($template, [
                'entity'     => $entity,
                'federation' => $federation,
                'contact'    => $contact,
                'errors'     => $errorMessages,
                'warnings'   => $warningMessages,
            ]);

            $result = $this->mailer->sendToEntity(
                $entity,
                $rendered['subject'],
                $rendered['body'],
                ['technical'],
                $federation,
                Auth::user(),
            );

            $totalSent    += $result['sent'];
            $totalFailed  += $result['failed'];
            $totalSkipped += $result['skipped'];
        }

        return redirect()->route('federations.show', $federation)
            ->with('success', "Compliance notifications sent: {$totalSent} delivered, {$totalFailed} failed, {$totalSkipped} skipped.")
            ->withFragment('tab-rules');
    }
}
