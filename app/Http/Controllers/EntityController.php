<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Entity\StoreEntityRequest;
use App\Http\Requests\Entity\UpdateEntityRequest;
use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\EntityAttribute;
use App\Models\EntityEndpoint;
use App\Models\EntityManager;
use App\Models\Federation;
use App\Services\Auth\FederationScopeService;
use App\Services\Entity\EntityMetadataService;
use App\Services\Entity\CertificateService;
use App\Services\Metadata\ExternalValidatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * EntityController
 *
 * Manages SAML2 entities (Identity Providers and Service Providers).
 *
 * Every stored entity maps 1:1 to a SAML2 <md:EntityDescriptor> element.
 * Field names and validation rules are derived from:
 *   - OASIS SAML Metadata 2.0 specification (saml-metadata-2.0-os)
 *   - eduGAIN Metadata Templates (IdP/SP) published by GÉANT
 *   - REFEDS Baseline Expectations v1 (IPO5/SPO5: metadata must be complete, accurate, up-to-date)
 *   - REFEDS specifications: R&S, SIRTFI, CoCo v2, MFA, entity categories
 *
 * XML namespace mapping:
 *   md    → urn:oasis:names:tc:SAML:2.0:metadata
 *   mdui  → urn:oasis:names:tc:SAML:metadata:ui
 *   mdrpi → urn:oasis:names:tc:SAML:metadata:rpi
 *   mdattr→ urn:oasis:names:tc:SAML:metadata:attribute
 *   shibmd→ urn:mace:shibboleth:metadata:1.0   (scope element)
 *   ds    → http://www.w3.org/2000/09/xmldsig#
 *   saml  → urn:oasis:names:tc:SAML:2.0:assertion
 */
class EntityController extends Controller
{
    public function __construct(
        private readonly EntityMetadataService $metadataService,
        private readonly CertificateService    $certificateService,
    ) {}

    public function index(Request $request)
    {
        Gate::authorize('entity.view');

        $scope = app(FederationScopeService::class);

        $entities = Entity::query()
            ->with(['federations', 'certificates'])
            ->tap(fn ($q) => $scope->scopeEntityQuery($q))
            ->when($request->type,   fn ($q, $v) => $q->where('type', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->search, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('entity_id', 'like', "%{$v}%")
                  ->orWhereHas('uiInfo', fn ($q) => $q->where('value', 'like', "%{$v}%")
                      ->whereIn('field', ['display_name', 'org_name']));
            }))
            ->orderBy('entity_id')
            ->paginate(25)
            ->withQueryString();

        $trashedCount = Entity::onlyTrashed()
            ->tap(fn ($q) => $scope->scopeEntityQuery($q))
            ->count();

        return view('entities.index', compact('entities', 'trashedCount'));
    }

    public function create()
    {
        Gate::authorize('entity.create');

        $federations = Federation::orderBy('name')->get(['id', 'name']);

        return view('entities.create', compact('federations'));
    }

    /**
     * Create a new SAML entity within a DB transaction.
     *
     * Automatically assigns the creator as owner EntityManager.
     * Runs metadata validation immediately after commit (persists EntityValidationResult).
     *
     * @authorizes  entity.create
     * @dispatches  ExternalValidatorService::runOnRegistration (post-commit, non-blocking)
     * @dispatches  NotificationService::entity_pending_approval (Guest / Entity Manager roles only)
     */
    public function store(StoreEntityRequest $request): RedirectResponse
    {
        Gate::authorize('entity.create');

        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $entity = Entity::create([
                'entity_id'                     => $validated['entity_id'],
                'type'                          => $validated['type'],
                'status'                        => 'draft',
                'edugain'                       => $validated['edugain'] ?? false,
                'registration_authority'        => config('federation.registration_authority') ?? '',
                'created_by'                    => Auth::id(),
                'scope'                         => $validated['scope'] ?? null,
                'nameid_formats'                => $validated['nameid_formats'] ?? [],
                'sp_want_authn_requests_signed' => $validated['sp_want_authn_requests_signed'] ?? true,
                'sp_want_assertions_signed'     => $validated['sp_want_assertions_signed'] ?? true,
                'requested_attributes'          => $validated['requested_attributes'] ?? [],
            ]);

            EntityManager::create([
                'entity_id' => $entity->id,
                'user_id'   => Auth::id(),
                'role'      => 'owner',
                'added_by'  => Auth::id(),
                'added_at'  => now(),
            ]);

            $this->syncUiInfo($entity, $validated);
            $this->syncContacts($entity, $validated);
            $this->syncEndpoints($entity, $validated);
            $this->syncAttributes($entity, $validated);

            // Store certificates — each row maps to one <md:KeyDescriptor>
            foreach (($validated['certificates'] ?? []) as $cert) {
                $parsed = $this->certificateService->parse($cert['pem']);

                $entity->certificates()->create([
                    'use'                 => $cert['use'],
                    'pem'                 => $cert['pem'],
                    'subject'             => $parsed['subject'],
                    'issuer'              => $parsed['issuer'],
                    'serial'              => $parsed['serial'],
                    'not_before'          => $parsed['not_before'],
                    'not_after'           => $parsed['not_after'],
                    'key_bits'            => $parsed['key_bits'],
                    'key_algorithm'       => $parsed['key_algorithm'],
                    'fingerprint'         => $parsed['fingerprint'],
                    'signature_algorithm' => $parsed['signature_algorithm'],
                    'debian_weak'         => $this->certificateService->isDebianWeak($parsed['fingerprint']),
                ]);
            }

            // Attach to federation(s)
            if (! empty($validated['federation_ids'])) {
                $entity->federations()->attach($validated['federation_ids'], [
                    'status'     => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Validate immediately — persists EntityValidationResult, non-blocking
            $validationResult = $this->metadataService->validate($entity);

            DB::commit();

            // Run federation validators after commit (non-blocking, logs + notifies FM on mandatory failure)
            if (! empty($validated['federation_ids'])) {
                $federation = Federation::find($validated['federation_ids'][0]);
                if ($federation) {
                    app(ExternalValidatorService::class)->runOnRegistration($entity->fresh(), $federation, 'registration');
                }
            }

            if (Auth::user()->hasAnyRole(['Guest', 'Entity Manager'])) {
                app(\App\Services\Notification\NotificationService::class)->dispatch(
                    'entity_pending_approval',
                    ['entity_name' => $entity->entity_id],
                    $entity
                );
            }

            Log::info('Entity created', ['entity_id' => $entity->entity_id, 'type' => $entity->type]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Entity creation failed', ['error' => $e->getMessage()]);

            return redirect()->back()
                ->withInput()
                ->withErrors(['general' => 'Entity could not be saved: ' . $e->getMessage()]);
        }

        return redirect()
            ->route('entities.show', $entity)
            ->with('success', 'Entity created. Metadata validation: ' . ($validationResult->passed() ? 'passed' : 'warnings present'));
    }

    /**
     * Show entity detail with cached metadata XML preview.
     *
     * @authorizes  entity.view + EntityPolicy::update (dual gate — rejects if user lacks ownership of this specific entity)
     */
    public function show(Entity $entity)
    {
        Gate::authorize('entity.view');
        Gate::authorize('update', $entity);

        $entity->load([
            'federations', 'certificates', 'uiInfo', 'contacts', 'endpoints', 'attributes',
            'auditLogs' => fn($q) => $q->latest()->limit(20),
        ]);

        $pendingInvitations = \App\Models\FederationEntityInvitation::where('entity_id', $entity->id)
            ->where('status', 'pending')
            ->with(['federation', 'invitedBy'])
            ->get();

        $metadataXml = Cache::remember(
            EntityMetadataService::xmlCacheKey($entity),
            EntityMetadataService::XML_CACHE_TTL,
            fn() => $this->metadataService->renderXml($entity)
        );

        $contactUserEmails = collect();
        if (auth()->user()?->hasRole('Admin') && $entity->contacts->isNotEmpty()) {
            $emails = $entity->contacts->pluck('email')->unique();
            $contactUserEmails = \App\Models\User::whereIn('email', $emails)->pluck('email');
        }

        return view('entities.show', compact('entity', 'metadataXml', 'pendingInvitations', 'contactUserEmails'));
    }

    public function edit(Entity $entity)
    {
        Gate::authorize('entity.edit');
        Gate::authorize('update', $entity);

        $entity->load(['federations', 'certificates', 'uiInfo', 'contacts', 'endpoints', 'attributes']);
        $federations = Federation::orderBy('name')->get(['id', 'name']);

        $pendingInvitations = \App\Models\FederationEntityInvitation::where('entity_id', $entity->id)
            ->where('status', 'pending')
            ->with(['federation', 'invitedBy'])
            ->get();

        return view('entities.edit', compact('entity', 'federations', 'pendingInvitations'));
    }

    /**
     * Update entity metadata fields. entity_id is immutable after creation.
     *
     * Intentional constraints:
     *   - entity_id (entityID) is immutable after creation.
     *   - Certificate rotation is managed via a dedicated certificate endpoint.
     *   - Status transitions are handled by separate action methods (reactivate, suspend).
     *
     * @dispatches  ExternalValidatorService::runOnRegistration (non-blocking, post-commit)
     */
    public function update(UpdateEntityRequest $request, Entity $entity): RedirectResponse
    {
        Gate::authorize('entity.edit');
        Gate::authorize('update', $entity);

        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $entity->update([
                'edugain'                       => $validated['edugain'] ?? false,
                'scope'                         => $validated['scope'] ?? null,
                'nameid_formats'                => $validated['nameid_formats'] ?? [],
                'sp_want_authn_requests_signed' => $validated['sp_want_authn_requests_signed'] ?? true,
                'sp_want_assertions_signed'     => $validated['sp_want_assertions_signed'] ?? true,
                'requested_attributes'          => $validated['requested_attributes'] ?? [],
                'last_updated_by'               => Auth::id(),
            ]);

            $this->syncUiInfo($entity, $validated);
            $this->syncContacts($entity, $validated);
            $this->syncEndpoints($entity, $validated);
            $this->syncAttributes($entity, $validated);

            // Re-validate after update — persists EntityValidationResult
            $this->metadataService->validate($entity->fresh());

            DB::commit();

            // Run federation validators on update (non-blocking)
            $activeFederation = $entity->federations()->first();
            if ($activeFederation) {
                app(ExternalValidatorService::class)->runOnRegistration($entity->fresh(), $activeFederation, 'update');
            }

            Log::info('Entity updated', ['entity_id' => $entity->entity_id]);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Entity update failed', ['entity_id' => $entity->entity_id, 'error' => $e->getMessage()]);

            return redirect()->back()
                ->withInput()
                ->withErrors(['general' => 'Update failed: ' . $e->getMessage()]);
        }

        return redirect()
            ->route('entities.show', $entity)
            ->with('success', 'Entity updated.');
    }

    /**
     * Soft-delete the entity. Blocked if status is 'active' — suspend the entity first.
     */
    public function destroy(Entity $entity): RedirectResponse
    {
        Gate::authorize('entity.delete');

        if ($entity->status === 'active') {
            return back()->with('error', __('app.entity_delete_blocked_active'));
        }

        $entityId = $entity->entity_id;
        $entity->delete();

        Log::info('Entity deleted', ['entity_id' => $entityId]);

        return redirect()->route('entities.index')
            ->with('success', "Entity {$entityId} deleted.");
    }

    public function trashed(): \Illuminate\Contracts\View\View
    {
        Gate::authorize('entity.view');

        $scope = app(FederationScopeService::class);

        $entities = Entity::onlyTrashed()
            ->with('uiInfo')
            ->tap(fn ($q) => $scope->scopeEntityQuery($q))
            ->paginate(20);

        return view('entities.trashed', compact('entities'));
    }

    /**
     * Restore a soft-deleted entity.
     *
     * If the entity was 'active' at deletion time it is restored as 'suspended' —
     * re-approval from the federation is required before it can be active again.
     */
    public function restore(string $id): RedirectResponse
    {
        Gate::authorize('entity.edit');

        $entity = Entity::onlyTrashed()->findOrFail($id);

        // Preserve the status at deletion time; only clamp 'active' → 'suspended'
        // since active membership requires federation re-approval after restore.
        $restoredStatus = $entity->status === 'active' ? 'suspended' : $entity->status;

        $entity->restore();

        if ($entity->status !== $restoredStatus) {
            $entity->update(['status' => $restoredStatus]);
        }

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => $entity->id,
            'action'     => 'entity_restored',
            'old_values' => null,
            'new_values' => ['entity_id' => $entity->entity_id, 'type' => $entity->type, 'status' => $restoredStatus],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return redirect()->route('entities.index')
            ->with('success', "Entity \"{$entity->entity_id}\" restored successfully.");
    }

    /**
     * Transition a suspended entity back to active. Blocked for any other status.
     */
    public function reactivate(Entity $entity): RedirectResponse
    {
        Gate::authorize('entity.edit');

        if ($entity->status !== 'suspended') {
            return back()->with('error', __('app.entity_reactivate_blocked'));
        }

        $entity->update(['status' => 'active']);

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => $entity->id,
            'action'     => 'entity_reactivated',
            'old_values' => ['status' => 'suspended'],
            'new_values' => ['status' => 'active'],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return redirect()->route('entities.show', $entity)
            ->with('success', __('app.entity_reactivated_success'));
    }

    /**
     * Permanently delete a soft-deleted entity. Blocked if it has active federation memberships.
     */
    public function forceDelete(string $id): RedirectResponse
    {
        Gate::authorize('entity.delete');

        $entity = Entity::onlyTrashed()->findOrFail($id);

        $activeFederations = $entity->federations()
            ->wherePivot('status', 'active')->count();
        if ($activeFederations > 0) {
            return back()->with('error', __('app.entity_force_delete_blocked'));
        }

        $entityId = $entity->entity_id;
        $entity->forceDelete();

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => null,
            'action'     => 'entity_force_deleted',
            'old_values' => ['entity_id' => $entityId],
            'new_values' => null,
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return redirect()->route('entities.trashed')
            ->with('success', "Entity \"{$entityId}\" permanently deleted.");
    }

    /**
     * Delete and re-create EntityUiInfo records from flat form fields.
     */
    private function syncUiInfo(Entity $entity, array $data): void
    {
        $entity->uiInfo()->delete();

        // English UIInfo fields
        $enFields = [
            'display_name'    => $data['name_en'] ?? null,
            'description'     => $data['description_en'] ?? null,
            'information_url' => $data['information_url_en'] ?? null,
            'privacy_url'     => $data['privacy_url_en'] ?? null,
            'org_name'        => $data['org_name_en'] ?? null,
            'org_display_name'=> $data['org_display_name_en'] ?? null,
            'org_url'         => $data['org_url_en'] ?? null,
        ];

        foreach ($enFields as $field => $value) {
            if ($value !== null && $value !== '') {
                $entity->uiInfo()->create(['field' => $field, 'lang' => 'en', 'value' => $value]);
            }
        }

        // Native language DisplayName / Description (when lang is provided)
        $nativeLang = $data['name_lang'] ?? null;
        if ($nativeLang) {
            if (! empty($data['name_native'])) {
                $entity->uiInfo()->create([
                    'field' => 'display_name', 'lang' => $nativeLang, 'value' => $data['name_native'],
                ]);
            }
            if (! empty($data['description_native'])) {
                $entity->uiInfo()->create([
                    'field' => 'description', 'lang' => $nativeLang, 'value' => $data['description_native'],
                ]);
            }
        }

        // Logo
        if (! empty($data['logo_url'])) {
            $entity->uiInfo()->create([
                'field'       => 'logo_url',
                'lang'        => 'en',
                'value'       => $data['logo_url'],
                'logo_height' => $data['logo_height'] ?? null,
                'logo_width'  => $data['logo_width'] ?? null,
            ]);
        }
    }

    /**
     * Delete and re-create EntityContact records from flat form fields.
     */
    private function syncContacts(Entity $entity, array $data): void
    {
        $entity->contacts()->delete();

        $contactTypes = [
            'technical' => $data['contact_technical_email'] ?? null,
            'support'   => $data['contact_support_email'] ?? null,
            'security'  => $data['contact_security_email'] ?? null,
        ];

        foreach ($contactTypes as $type => $email) {
            if (! empty($email)) {
                $entity->contacts()->create(['type' => $type, 'email' => $email]);
            }
        }
    }

    /**
     * Delete and re-create EntityEndpoint records from flat form fields.
     */
    private function syncEndpoints(Entity $entity, array $data): void
    {
        $entity->endpoints()->delete();

        // SSO (IdP)
        $ssoMap = [
            EntityEndpoint::BINDING_HTTP_POST     => $data['sso_http_post'] ?? null,
            EntityEndpoint::BINDING_HTTP_REDIRECT => $data['sso_http_redirect'] ?? null,
            EntityEndpoint::BINDING_SOAP          => $data['sso_soap'] ?? null,
        ];
        foreach ($ssoMap as $binding => $location) {
            if (! empty($location)) {
                $entity->endpoints()->create(['type' => 'sso', 'binding' => $binding, 'location' => $location]);
            }
        }

        // ACS (SP) — indexed, first one is default
        $acsIndex = 1;
        $acsMap   = [
            EntityEndpoint::BINDING_HTTP_POST     => $data['acs_http_post'] ?? null,
            EntityEndpoint::BINDING_HTTP_REDIRECT => $data['acs_http_redirect'] ?? null,
            EntityEndpoint::BINDING_PAOS          => $data['acs_paos'] ?? null,
        ];
        foreach ($acsMap as $binding => $location) {
            if (! empty($location)) {
                $entity->endpoints()->create([
                    'type'       => 'acs',
                    'binding'    => $binding,
                    'location'   => $location,
                    'index'      => $acsIndex,
                    'is_default' => $acsIndex === 1,
                ]);
                $acsIndex++;
            }
        }

        // SLO (both IdP and SP)
        $sloMap = [
            EntityEndpoint::BINDING_HTTP_POST     => $data['slo_http_post'] ?? null,
            EntityEndpoint::BINDING_HTTP_REDIRECT => $data['slo_http_redirect'] ?? null,
            EntityEndpoint::BINDING_SOAP          => $data['slo_soap'] ?? null,
        ];
        foreach ($sloMap as $binding => $location) {
            if (! empty($location)) {
                $entity->endpoints()->create(['type' => 'slo', 'binding' => $binding, 'location' => $location]);
            }
        }
    }

    /**
     * Delete and re-create EntityAttribute records from flat form fields.
     */
    private function syncAttributes(Entity $entity, array $data): void
    {
        $entity->attributes()->delete();

        foreach ($data['entity_categories'] ?? [] as $uri) {
            $entity->attributes()->create([
                'attribute_name'  => EntityAttribute::ATTR_ENTITY_CATEGORY,
                'attribute_value' => $uri,
            ]);
        }

        foreach ($data['assurance_profiles'] ?? [] as $uri) {
            $entity->attributes()->create([
                'attribute_name'  => EntityAttribute::ATTR_ASSURANCE_PROFILE,
                'attribute_value' => $uri,
            ]);
        }

        // SIRTFI boolean flag → assurance_profile attribute
        if (! empty($data['sirtfi'])) {
            $alreadySet = $entity->attributes()
                ->where('attribute_name', EntityAttribute::ATTR_ASSURANCE_PROFILE)
                ->where('attribute_value', EntityAttribute::URI_SIRTFI)
                ->exists();

            if (! $alreadySet) {
                $entity->attributes()->create([
                    'attribute_name'  => EntityAttribute::ATTR_ASSURANCE_PROFILE,
                    'attribute_value' => EntityAttribute::URI_SIRTFI,
                ]);
            }
        }
    }
}
