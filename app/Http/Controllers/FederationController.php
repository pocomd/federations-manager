<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Federation\StoreFederationRequest;
use App\Http\Requests\Federation\UpdateFederationRequest;
use App\Jobs\GenerateMetadataJob;
use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\Federation;
use App\Models\SystemPreference;
use App\Models\User;
use App\Services\Auth\FederationScopeService;
use App\Services\Entity\EntityMetadataService;
use App\Services\Metadata\ExternalValidatorService;
use App\Services\Signing\SigningDriverFactory;
use App\Services\Webhook\WebhookService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * FederationController
 *
 * Manages SAML2 federations — the organisational units that group entities
 * and produce signed aggregate metadata (EntitiesDescriptor documents).
 *
 * Entity membership lifecycle:
 *   Entity created → addEntity() → status=pending
 *                 → approveEntity() → status=active   (entity included in metadata)
 *                 → rejectEntity()  → status=rejected (entity excluded from metadata)
 *                 → removeEntity()  → pivot row deleted
 *
 * Metadata generation:
 *   generateMetadata() dispatches GenerateMetadataJob which:
 *     - Collects all entities with pivot status=active
 *     - Builds <md:EntitiesDescriptor> aggregate XML
 *     - Signs with xmlsectool (if configured)
 *     - Caches result for 6 hours
 */
class FederationController extends Controller
{

    public function index(): View
    {
        Gate::authorize('federation.view');

        $counts = [
            'entities',
            'entities as active_entities_count'  => fn ($q) => $q->where('entity_federation.status', 'active'),
            'entities as pending_entities_count' => fn ($q) => $q->where('entity_federation.status', 'pending'),
        ];

        $federations = app(FederationScopeService::class)
            ->scopeQuery(Federation::withCount($counts)->orderBy('name'))
            ->paginate(25);

        $trashedCount = Federation::onlyTrashed()->count();

        return view('federations.index', compact('federations', 'trashedCount'));
    }

    public function create(): View
    {
        Gate::authorize('federation.create');

        $signingDrivers = app(SigningDriverFactory::class)->activeDrivers();

        return view('federations.create', compact('signingDrivers'));
    }

    public function store(StoreFederationRequest $request): RedirectResponse
    {
        Gate::authorize('federation.create');

        $federation = Federation::create($request->validated());

        Log::info('Federation created', ['federation_id' => $federation->id, 'uri' => $federation->uri]);

        return redirect()
            ->route('federations.show', $federation)
            ->with('success', "Federation \"{$federation->name}\" created successfully.");
    }

    /**
     * Show federation detail page.
     *
     * Loads manager assignments including who assigned each manager (for display).
     * Membership and attributes tabs are owned by Livewire components — only stat counts are fetched here.
     */
    public function show(Federation $federation): View
    {
        Gate::authorize('federation.view');

        $federation->load([
            'registrationPolicies',
            'validators',
            'ruleConfigs.rule',
            'managers',
        ]);

        // Counts for the General tab stats, pie chart, and tab badges.
        // Membership and attributes tabs are Livewire components that own their data.
        $idpCount      = $federation->entities()->where('entities.type', 'idp')->wherePivot('status', 'active')->count();
        $spCount       = $federation->entities()->where('entities.type', 'sp')->wherePivot('status', 'active')->count();
        $pendingCount  = $federation->entities()->wherePivot('status', 'pending')->count();
        $attributeCount = $federation->requiredAttributes()->count();

        $managers        = $federation->managers;
        $managerCount    = $managers->count();
        $assignedByNames = User::whereIn('id', $managers->pluck('pivot.assigned_by')->filter()->unique()->values())
            ->pluck('name', 'id');
        $availableManagers = User::role('Federation Manager')
            ->whereNotIn('id', $managers->pluck('id'))
            ->orderBy('name')
            ->get();

        return view('federations.show', compact(
            'federation',
            'idpCount',
            'spCount',
            'pendingCount',
            'attributeCount',
            'managers',
            'managerCount',
            'assignedByNames',
            'availableManagers',
        ));
    }

    /**
     * Download active entity contacts as a CSV or plain-text file.
     *
     * Supports filtering by entity type (all/idp/sp), contact type, and unique-email deduplication.
     * Format is controlled by the ?format= query param (csv or txt, defaults to txt).
     */
    public function downloadContacts(Request $request, Federation $federation): \Illuminate\Http\Response
    {
        Gate::authorize('federation.view');

        $entityType  = $request->query('type', 'all');
        $contactType = $request->query('contact_type', 'all');
        $format      = $request->query('format', 'txt');
        $unique      = (bool) $request->query('unique', false);

        $query = $federation->entities()
            ->wherePivot('status', 'active')
            ->with(['uiInfo', 'contacts' => function ($q) use ($contactType) {
                if ($contactType !== 'all') {
                    $q->where('type', $contactType);
                }
            }]);

        if ($entityType !== 'all') {
            $query->where('type', $entityType);
        }

        $entities = $query->get();

        $seenEmails = [];

        if ($format === 'csv') {
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, ['Entity Type', 'Entity Name', 'Entity ID', 'Contact Type', 'Contact Name', 'Contact Email']);

            foreach ($entities as $entity) {
                $entityName = $entity->getDisplayName() ?? $entity->entity_id;
                foreach ($entity->contacts as $contact) {
                    if ($unique) {
                        if (isset($seenEmails[$contact->email])) continue;
                        $seenEmails[$contact->email] = true;
                    }
                    fputcsv($handle, [
                        strtoupper($entity->type),
                        $entityName,
                        $entity->entity_id,
                        $contact->type,
                        trim($contact->given_name . ' ' . $contact->sur_name),
                        $contact->email,
                    ]);
                }
            }

            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);

            $filename = 'contacts-' . $entityType . '-' . $contactType . '-' . date('Y-m-d') . '.csv';

            return response($content, 200, [
                'Content-Type'        => 'text/csv; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        }

        // Plain text
        $lines   = [];
        $lines[] = 'Federation: ' . $federation->name;
        $lines[] = 'Exported:   ' . now()->toDateTimeString();
        $lines[] = 'Filters:    entity=' . $entityType . ', contact=' . $contactType . ($unique ? ', unique emails' : '');
        $lines[] = str_repeat('=', 60);

        foreach ($entities as $entity) {
            $name = $entity->getDisplayName() ?? $entity->entity_id;
            $lines[] = '';
            $lines[] = str_repeat('-', 60);
            $lines[] = strtoupper($entity->type) . ': ' . $name;
            $lines[] = 'Entity ID:  ' . $entity->entity_id;
            foreach ($entity->contacts as $contact) {
                if ($unique) {
                    if (isset($seenEmails[$contact->email])) continue;
                    $seenEmails[$contact->email] = true;
                }
                $fullName = trim($contact->given_name . ' ' . $contact->sur_name);
                $lines[]  = ucfirst($contact->type) . ': ' . $fullName . ' <' . $contact->email . '>';
            }
        }

        $content  = implode("\n", $lines);
        $filename = 'contacts-' . $entityType . '-' . $contactType . '-' . date('Y-m-d') . '.txt';

        return response($content, 200, [
            'Content-Type'        => 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function edit(Federation $federation): View
    {
        Gate::authorize('update', $federation);

        return view('federations.edit', compact('federation'));
    }

    /**
     * Update federation settings.
     *
     * If the status transitions from inactive → active, notifies all assigned managers.
     *
     * @notify  AppNotification::federation_reactivated (assigned managers, on inactive→active transition only)
     */
    public function update(UpdateFederationRequest $request, Federation $federation): RedirectResponse
    {
        Gate::authorize('update', $federation);

        $oldStatus = $federation->status;

        $federation->update($request->validated());

        if ($oldStatus === 'inactive' && $federation->status === 'active') {
            AuditLog::create([
                'user_id'    => Auth::id(),
                'entity_id'  => null,
                'action'     => 'federation_reactivated',
                'old_values' => ['status' => 'inactive'],
                'new_values' => ['status' => 'active', 'federation_id' => $federation->id, 'name' => $federation->name],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            foreach ($federation->managers as $manager) {
                AppNotification::create([
                    'user_id'      => $manager->id,
                    'type'         => 'federation_reactivated',
                    'title'        => "Federation \"{$federation->name}\" reactivated",
                    'body'         => "The federation \"{$federation->name}\" has been set back to Active and will be included in the next metadata generation cycle.",
                    'subject_type' => 'federation',
                    'subject_id'   => $federation->id,
                    'action_url'   => route('federations.show', $federation),
                ]);
            }
        }

        Log::info('Federation updated', ['federation_id' => $federation->id]);

        return redirect()
            ->route('federations.show', $federation)
            ->with('success', "Federation \"{$federation->name}\" updated.");
    }

    /**
     * Soft-delete the federation. Blocked if status is 'active'.
     *
     * Notifies all currently assigned managers that the federation has been deleted.
     *
     * @notify  AppNotification::federation_deleted (all assigned managers)
     */
    public function destroy(Federation $federation): RedirectResponse
    {
        Gate::authorize('update', $federation);

        if ($federation->status === 'active') {
            return back()->with('error', __('app.federation_delete_blocked_active'));
        }

        $name      = $federation->name;
        $fedId     = $federation->id;
        $managers  = $federation->managers()->get();

        $federation->delete();

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => null,
            'action'     => 'federation_deleted',
            'old_values' => ['federation_id' => $fedId, 'name' => $name],
            'new_values' => null,
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        foreach ($managers as $manager) {
            AppNotification::create([
                'user_id'      => $manager->id,
                'type'         => 'federation_deleted',
                'title'        => "Federation \"{$name}\" has been deleted",
                'body'         => "The federation you managed, \"{$name}\", has been deleted and moved to the trash. Contact an administrator if this was unintended.",
                'subject_type' => 'federation',
                'subject_id'   => $fedId,
                'action_url'   => route('federations.trashed'),
            ]);
        }

        Log::info('Federation soft-deleted', ['federation_id' => $fedId]);

        return redirect()
            ->route('federations.index')
            ->with('success', "Federation \"{$name}\" deleted.");
    }

    public function trashed(): View
    {
        Gate::authorize('federation.create');

        $federations = Federation::onlyTrashed()
            ->withCount('entities')
            ->paginate(20);

        return view('federations.trashed', compact('federations'));
    }

    public function restore(string $id): RedirectResponse
    {
        Gate::authorize('federation.create');

        $federation = Federation::onlyTrashed()->findOrFail($id);
        $federation->restore();

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => null,
            'action'     => 'federation_restored',
            'old_values' => null,
            'new_values' => ['federation_id' => $federation->id, 'name' => $federation->name],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return redirect()->route('federations.index')
            ->with('success', "Federation \"{$federation->name}\" restored successfully.");
    }

    /**
     * Permanently delete a soft-deleted federation. Blocked if it has active member entities.
     *
     * Also removes signing key files from disk at storage/app/signing-keys/{id}/.
     *
     * @sideeffects  Deletes signing.key and signing.crt from disk after the DB record is gone
     */
    public function forceDelete(string $id): RedirectResponse
    {
        Gate::authorize('federation.create');

        $federation = Federation::onlyTrashed()->findOrFail($id);

        $activeMembers = $federation->entities()->wherePivot('status', 'active')->count();
        if ($activeMembers > 0) {
            return back()->with('error', __('app.federation_force_delete_blocked'));
        }

        $name = $federation->name;

        // Clean up driver credentials before destroying the DB record
        try {
            app(SigningDriverFactory::class)->make($federation)->deleteAll($federation);
        } catch (\Throwable) {
            // Non-fatal — continue with force delete even if credential cleanup fails
        }

        $federation->forceDelete();

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => null,
            'action'     => 'federation_force_deleted',
            'old_values' => ['federation_id' => $id, 'name' => $name],
            'new_values' => null,
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return redirect()->route('federations.trashed')
            ->with('success', "Federation \"{$name}\" permanently deleted.");
    }

    /**
     * Attach an entity to the federation with status=pending (idempotent — no duplicate pivot rows).
     * The entity is not included in published metadata until approved.
     */
    public function addEntity(Federation $federation, Entity $entity): RedirectResponse
    {
        Gate::authorize('entity.addToFederation');

        // Idempotent: do not create a duplicate pivot row
        if ($federation->entities()->where('entities.id', $entity->id)->exists()) {
            return redirect()->back()
                ->with('error', 'This entity is already a member of the federation.');
        }

        $expiryDays = (int) SystemPreference::get('pending_membership_expiry_days', 7);
        $federation->entities()->attach($entity->id, [
            'status'     => 'pending',
            'expires_at' => now()->addDays($expiryDays),
        ]);

        Log::info('Entity added to federation (pending)', [
            'federation_id' => $federation->id,
            'entity_id'     => $entity->entity_id,
        ]);

        return redirect()->back()
            ->with('success', "Entity \"{$entity->entity_id}\" added — awaiting approval.");
    }

    /**
     * Detach an entity from the federation (removes pivot row entirely).
     */
    public function removeEntity(Federation $federation, Entity $entity): RedirectResponse
    {
        Gate::authorize('entity.removeFromFederation');

        $federation->entities()->detach($entity->id);

        Cache::forget("federation_metadata:{$federation->id}");
        Cache::forget("federation_edugain_metadata:{$federation->id}");

        Log::info('Entity removed from federation', [
            'federation_id' => $federation->id,
            'entity_id'     => $entity->entity_id,
        ]);

        return redirect()->back()
            ->with('success', "Entity \"{$entity->entity_id}\" removed from federation.");
    }

    /**
     * Approve a pending entity membership — pivot status becomes active.
     *
     * Also promotes entity status from draft/pending → active on first approved membership.
     * The entity will be included in the next generated metadata.
     *
     * @dispatches  NotificationService::entity_approved
     * @dispatches  ExternalValidatorService::runOnRegistration (approval stage, non-blocking)
     * @dispatches  WebhookService::entity.approved
     */
    public function approveEntity(Federation $federation, Entity $entity): RedirectResponse
    {
        Gate::authorize('federation.approveRequest');

        if (\App\Models\EntityFederation::hasActiveMembership($entity->id, $federation->id)) {
            return redirect()->back()->withErrors(['entity' => 'Entity already has an active membership in another federation.']);
        }

        $federation->entities()->updateExistingPivot($entity->id, [
            'status'      => 'active',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        Cache::forget("federation_metadata:{$federation->id}");
        Cache::forget("federation_edugain_metadata:{$federation->id}");

        // Promote entity from draft/pending → active on first approved membership
        if (in_array($entity->status, ['draft', 'pending'], true)) {
            $entity->update(['status' => 'active']);
        }

        app(\App\Services\Notification\NotificationService::class)->dispatch(
            'entity_approved',
            ['entity_name' => $entity->entity_id],
            $entity,
            $federation
        );

        // Run enabled_on_registration validators on approval (non-blocking)
        app(ExternalValidatorService::class)->runOnRegistration($entity, $federation, 'approval');

        app(WebhookService::class)->dispatch('entity.approved', [
            'entity_id'     => $entity->entity_id,
            'federation_id' => $federation->id,
            'federation'    => $federation->uri,
        ]);

        Log::info('Entity membership approved', [
            'federation_id' => $federation->id,
            'entity_id'     => $entity->entity_id,
            'approved_by'   => Auth::id(),
        ]);

        return redirect()->back()
            ->with('success', "Entity \"{$entity->entity_id}\" approved for federation.");
    }

    /**
     * Reject a pending entity membership — pivot status becomes rejected.
     *
     * The entity remains in the database but is excluded from metadata.
     *
     * @dispatches  NotificationService::entity_rejected
     */
    public function rejectEntity(Federation $federation, Entity $entity): RedirectResponse
    {
        Gate::authorize('federation.rejectRequest');

        $federation->entities()->updateExistingPivot($entity->id, [
            'status'           => 'rejected',
            'rejection_reason' => request('reason', '') ?: null,
        ]);

        Cache::forget("federation_metadata:{$federation->id}");
        Cache::forget("federation_edugain_metadata:{$federation->id}");

        app(\App\Services\Notification\NotificationService::class)->dispatch(
            'entity_rejected',
            ['entity_name' => $entity->entity_id, 'reason' => request('reason', '')],
            $entity,
            $federation
        );

        Log::info('Entity membership rejected', [
            'federation_id' => $federation->id,
            'entity_id'     => $entity->entity_id,
            'reason'        => request('reason', ''),
        ]);

        return redirect()->back()
            ->with('success', "Entity \"{$entity->entity_id}\" membership rejected.");
    }

    public function downloadSigningCert(Federation $federation): \Illuminate\Http\Response|RedirectResponse
    {
        Gate::authorize('update', $federation);

        $driver = app(SigningDriverFactory::class)->make($federation);
        $info   = $driver->certInfo($federation);

        if (! $info || empty($info['pem'])) {
            return redirect()->back()->with('error', 'No signing certificate found for this federation.');
        }

        $filename = $federation->slug . '-signing.crt';

        return response($info['pem'], 200, [
            'Content-Type'        => 'application/x-pem-file',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function updateJaggerCompat(Request $request, Federation $federation): RedirectResponse
    {
        Gate::authorize('update', $federation);

        $validated = $request->validate([
            'jagger_compat_enabled' => ['boolean'],
            'jagger_fed_name'       => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9_\-]+$/',
                \Illuminate\Validation\Rule::unique('federations', 'jagger_fed_name')->ignore($federation->id),
            ],
        ]);

        $federation->update([
            'jagger_compat_enabled' => $request->boolean('jagger_compat_enabled'),
            'jagger_fed_name'       => $validated['jagger_fed_name'] ?: null,
        ]);

        return redirect()->back()->with('success', 'Jagger compatibility settings saved.');
    }

    /**
     * Assign an existing user as a federation manager (idempotent via syncWithoutDetaching).
     *
     * @notify  AppNotification::federation_manager_assigned (to the newly assigned user)
     */
    public function addManager(Request $request, Federation $federation): RedirectResponse
    {
        Gate::authorize('federation.create');

        $validated = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
        ]);

        $federation->managers()->syncWithoutDetaching([
            $validated['user_id'] => [
                'assigned_by' => Auth::id(),
                'assigned_at' => now(),
            ],
        ]);

        AppNotification::create([
            'user_id'      => $validated['user_id'],
            'type'         => 'federation_manager_assigned',
            'title'        => 'You have been assigned as federation manager',
            'body'         => "You are now a manager of \"{$federation->name}\". Your new permissions are active immediately.",
            'subject_type' => 'federation',
            'subject_id'   => $federation->id,
            'action_url'   => route('federations.show', $federation),
        ]);

        return redirect()->back()->with('success', 'Manager assigned.');
    }

    /**
     * Remove a federation manager. Blocked if they are the last manager.
     *
     * Sets a force_logout cache key so the removed user is signed out on their next request.
     *
     * @sideeffects  Cache::put("force_logout_{userId}") for 24 hours
     */
    public function removeManager(Federation $federation, User $user): RedirectResponse
    {
        Gate::authorize('federation.create');

        if ($federation->managers()->count() <= 1) {
            return redirect()->back()->with('error', 'Cannot remove the last manager. Assign another manager first.');
        }

        $federation->managers()->detach($user->id);

        \Illuminate\Support\Facades\Cache::put("force_logout_{$user->id}", true, now()->addHours(24));

        return redirect()->back()->with('success', 'Manager removed.');
    }

    /**
     * Generate (or return cached) signed aggregate metadata for this federation.
     *
     * Runs GenerateMetadataJob synchronously only if the cache is cold; otherwise serves the cached XML.
     * Returns the XML as application/samlmetadata+xml for direct download or federation consumption.
     *
     * @dispatches  GenerateMetadataJob::dispatchSync (only on cache miss)
     * @dispatches  WebhookService::metadata.generated
     */
    public function generateMetadata(Federation $federation): Response
    {
        Gate::authorize('metadata.generate');

        // Run the job synchronously so the cache is populated before we respond.
        // In production, QUEUE_CONNECTION=redis means the job runs in the background
        // on the next hit; for the initial call we ensure the cache is warm.
        $cacheKey = "federation_metadata:{$federation->id}";

        if (! Cache::has($cacheKey)) {
            GenerateMetadataJob::dispatchSync($federation->id);
        }

        $xml = Cache::get(
            $cacheKey,
            '<?xml version="1.0" encoding="UTF-8"?>' .
            '<md:EntitiesDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata" ' .
            'Name="' . htmlspecialchars($federation->uri, ENT_XML1) . '"/>',
        );

        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $federation->name) . '-metadata.xml';

        app(WebhookService::class)->dispatch('metadata.generated', [
            'federation_id' => $federation->id,
            'federation'    => $federation->uri,
        ]);

        return response($xml, 200)
            ->header('Content-Type', 'application/samlmetadata+xml')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

    public function revalidateEntities(Federation $federation, EntityMetadataService $metadataService): RedirectResponse|View
    {
        Gate::authorize('update', $federation);

        $entities = $federation->entities()
            ->wherePivot('status', 'active')
            ->where('entities.status', 'active')
            ->with(['certificates', 'uiInfo', 'contacts', 'endpoints', 'attributes', 'federations'])
            ->get();

        if ($entities->isEmpty()) {
            return back()->with('info', 'No active entities in this federation.');
        }

        $failures = [];

        foreach ($entities as $entity) {
            $result = $metadataService->validate($entity);

            if (! $result->passed()) {
                $failures[] = [
                    'entity'   => $entity,
                    'errors'   => $result->errors(),
                    'warnings' => $result->warnings(),
                ];
            }
        }

        if (empty($failures)) {
            return back()->with('success', "All {$entities->count()} entities passed compliance checks.");
        }

        return view('federations.revalidate-results', compact('federation', 'entities', 'failures'));
    }

}
