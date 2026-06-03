<?php

declare(strict_types=1);

use App\Http\Controllers\ArpController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HealthUiController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\StatisticsController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\InvitationRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationPreferenceController;
use App\Http\Controllers\EntityRuleConfigController;
use App\Http\Controllers\FederationRuleConfigController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\AttributeDefinitionController;
use App\Http\Controllers\RuleDefinitionController;
use App\Http\Controllers\EduGainController;
use App\Http\Controllers\FederationMailController;
use App\Http\Controllers\FederationMailTemplateController;
use App\Http\Controllers\FederationRequiredAttributeController;
use App\Http\Controllers\FederationValidatorController;
use App\Http\Controllers\MailTemplateController;
use App\Http\Controllers\RegistrationPolicyController;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Auth\InvitationRegistrationController;
use App\Http\Controllers\Auth\SamlAuthController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CertificateMonitoringController;
use App\Http\Controllers\EntityController;
use App\Http\Controllers\EntityImportController;
use App\Http\Controllers\JaggerImportController;
use App\Http\Controllers\EntityMetadataController;
use App\Http\Controllers\EntityRequestedAttributesController;
use App\Http\Controllers\ComplianceNotifyController;
use App\Http\Controllers\FederationController;
use App\Http\Controllers\FederationEntityInvitationController;
use App\Http\Controllers\MetadataGenerationController;
use App\Http\Controllers\SchedulerController;
use App\Http\Controllers\SystemPreferencesController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureAuthenticated;
use Illuminate\Support\Facades\Route;

// ── WebFinger discovery (RFC 7033 — public, no auth) ─────────────────────────
Route::get('/.well-known/webfinger', [\App\Http\Controllers\DiscoveryController::class, 'webFinger'])
    ->name('webfinger');

// ── Language switcher (no auth — works on login page too) ───────────────────
Route::get('/language/{lang}', [LanguageController::class, 'switch'])
    ->name('language.switch');

// ── Auth — public routes ────────────────────────────────────────────────────────

// Login page — shows SAML button + local email/password form
Route::get('login', [SamlAuthController::class, 'showLogin'])->name('login');

// Local (admin) email + password login
Route::post('login', [SamlAuthController::class, 'localLogin'])->name('login.local');

// SAML: redirect to IdP via SimpleSAMLphp
Route::get('saml/login', [SamlAuthController::class, 'login'])->name('saml.login');

// SAML: callback after SimpleSAMLphp authenticates — reads SP session attributes
Route::post('saml/acs', [SamlAuthController::class, 'acs'])->name('saml.acs');

// Global logout (Laravel session + SimpleSAMLphp SLO)
Route::post('logout', [SamlAuthController::class, 'logout'])->name('logout');

// ── Invitation registration (public — no auth required) ─────────────────────
Route::get('/register/{token}', [InvitationRegistrationController::class, 'show'])
    ->name('register.invitation.show');
Route::post('/register/{token}', [InvitationRegistrationController::class, 'register'])
    ->name('register.invitation.register');

// ── Authenticated web routes ───────────────────────────────────────────────────
Route::middleware(EnsureAuthenticated::class)->group(function () {

    // ── Root redirect → dashboard ─────────────────────────────────────────────
    Route::get('/', fn () => redirect()->route('dashboard'));

    // ── User Guide ────────────────────────────────────────────────────────────
    Route::get('/guide',                         [GuideController::class, 'index'])->name('guide.index');
    Route::get('/guide/operations/{group}',      [GuideController::class, 'showOperations'])->name('guide.operations');
    Route::get('/guide/{page}',                  [GuideController::class, 'show'])->name('guide.show');

    // ── Dashboard ─────────────────────────────────────────────────────────────
    Route::get('/dashboard', function () {
        $scope = app(\App\Services\Auth\FederationScopeService::class);

        // Reusable scoped query factories
        $entityBase = fn () => $scope->scopeEntityQuery(\App\Models\Entity::query());
        $certBase   = fn () => \App\Models\EntityCertificate::query()
            ->whereHas('entity', fn ($q) => $scope->scopeEntityQuery($q->where('status', 'active')));

        // Audit logs: FMs see only logs tied to entities in their federations
        $auditQuery = \App\Models\AuditLog::with(['user', 'entity'])->latest();
        if ($scope->isConstrained()) {
            $auditQuery->whereIn('entity_id', $entityBase()->pluck('id'));
        }

        return view('dashboard', [
            'totalEntities'    => $entityBase()->count(),
            'activeEntities'   => $entityBase()->where('status', 'active')->count(),
            'totalFederations' => $scope->scopeQuery(\App\Models\Federation::query())->count(),
            'criticalCerts'    => $certBase()->critical()->count(),
            'recentEntities'   => $entityBase()->with('uiInfo')->latest()->limit(5)->get(),
            'recentAuditLogs'  => $auditQuery->limit(5)->get(),
            'certSummary'      => [
                'expired'  => $certBase()->expired()->count(),
                'critical' => $certBase()->critical()->count(),
                'warning'  => $certBase()->warning()->count(),
                'advisory' => $certBase()->expiring(60)->count(),
            ],
        ]);
    })->name('dashboard');

    // ── Entity trash (must be before resource to avoid conflicts) ────────────
    Route::get('/entities/trashed',          [EntityController::class, 'trashed'])->name('entities.trashed');
    Route::post('/entities/{id}/restore',    [EntityController::class, 'restore'])->name('entities.restore');
    Route::delete('/entities/{id}/force-delete', [EntityController::class, 'forceDelete'])->name('entities.force-delete');

    // ── Entity import (must be before resource to avoid conflicts) ────────────
    Route::prefix('entities/import')->name('entities.import.')->group(function () {
        Route::get('/xml',      [EntityImportController::class, 'showXmlImport'])->name('xml');
        Route::post('/xml',     [EntityImportController::class, 'importFromXml'])->name('xml.submit');
        Route::get('/array',    [EntityImportController::class, 'showArrayImport'])->name('array');
        Route::post('/array',   [EntityImportController::class, 'importFromArray'])->name('array.submit');
        Route::get('/preview',  [EntityImportController::class, 'previewImport'])->name('preview');
        Route::post('/confirm', [EntityImportController::class, 'confirmImport'])->name('confirm');
    });

    // ── Entity metadata reload ────────────────────────────────────────────────
    Route::post('/entities/{entity}/reload',
        [EntityImportController::class, 'reloadEntity'])
        ->name('entities.reload');

    // ── Entity state transitions ──────────────────────────────────────────────
    Route::post('/entities/{entity}/reactivate',
        [EntityController::class, 'reactivate'])
        ->name('entities.reactivate')
        ->withTrashed();

    // ── Entity CRUD (standard resource) ───────────────────────────────────────
    Route::resource('entities', EntityController::class);

    // ── Federation trash (must be before resource to avoid conflicts) ────────
    Route::get('/federations/trashed',          [FederationController::class, 'trashed'])->name('federations.trashed');
    Route::post('/federations/{id}/restore',    [FederationController::class, 'restore'])->name('federations.restore');
    Route::delete('/federations/{id}/force-delete', [FederationController::class, 'forceDelete'])->name('federations.force-delete');

    // ── Federation CRUD ───────────────────────────────────────────────────────
    // edit route removed — inline editing on the show page replaces the separate edit page
    Route::resource('federations', FederationController::class)->except(['edit']);

    // ── Federation entity membership actions ──────────────────────────────────
    Route::post(
        'federations/{federation}/entities/{entity}',
        [FederationController::class, 'addEntity']
    )->name('federations.entities.add');

    Route::delete(
        'federations/{federation}/entities/{entity}',
        [FederationController::class, 'removeEntity']
    )->name('federations.entities.remove');

    Route::patch(
        'federations/{federation}/entities/{entity}/approve',
        [FederationController::class, 'approveEntity']
    )->name('federations.entities.approve');

    Route::patch(
        'federations/{federation}/entities/{entity}/reject',
        [FederationController::class, 'rejectEntity']
    )->name('federations.entities.reject');

    // ── Federation manager assignment ─────────────────────────────────────────
    Route::get(
        'federations/{federation}/managers',
        fn (\App\Models\Federation $federation) => redirect()->route('federations.show', $federation)
    )->name('federations.managers.index');

    Route::post(
        'federations/{federation}/managers',
        [FederationController::class, 'addManager']
    )->name('federations.managers.add');

    Route::delete(
        'federations/{federation}/managers/{user}',
        [FederationController::class, 'removeManager']
    )->name('federations.managers.remove');

    // ── Federation entity invitations (FM → Entity Manager) ──────────────────
    Route::patch(
        'federation-entity-invitations/{invitation}/accept',
        [FederationEntityInvitationController::class, 'accept']
    )->name('federation-entity-invitations.accept');

    Route::patch(
        'federation-entity-invitations/{invitation}/reject',
        [FederationEntityInvitationController::class, 'reject']
    )->name('federation-entity-invitations.reject');

    Route::delete(
        'federation-entity-invitations/{invitation}',
        [FederationEntityInvitationController::class, 'cancel']
    )->name('federation-entity-invitations.cancel');

    // ── Federation signing certificate download ───────────────────────────────
    Route::get(
        'federations/{federation}/signing-cert/download',
        [FederationController::class, 'downloadSigningCert']
    )->name('federations.signing-cert.download');

    // ── Jagger compatibility settings ─────────────────────────────────────────
    Route::patch(
        'federations/{federation}/jagger-compat',
        [FederationController::class, 'updateJaggerCompat']
    )->name('federations.jagger-compat');

    // ── Federation contacts download ──────────────────────────────────────────
    Route::get(
        'federations/{federation}/contacts/download',
        [FederationController::class, 'downloadContacts']
    )->name('federations.contacts.download');

    // ── Re-validate all entities in a federation ──────────────────────────────
    Route::post(
        'federations/{federation}/revalidate',
        [FederationController::class, 'revalidateEntities']
    )->name('federations.revalidate');

    // ── Compliance failure notifications ──────────────────────────────────────
    Route::post(
        'federations/{federation}/compliance-notify',
        [ComplianceNotifyController::class, 'sendFederation']
    )->name('federations.compliance-notify');

    // ── Federation required attributes ────────────────────────────────────────
    Route::post(
        'federations/{federation}/attributes',
        [FederationRequiredAttributeController::class, 'store']
    )->name('federations.attributes.store');
    Route::delete(
        'federations/{federation}/attributes/{attribute}',
        [FederationRequiredAttributeController::class, 'destroy']
    )->name('federations.attributes.destroy');

    // ── Metadata management ───────────────────────────────────────────────────
    Route::get('/metadata', [MetadataGenerationController::class, 'index'])
        ->name('metadata.index');

    Route::post('/metadata/{federation}/generate', [MetadataGenerationController::class, 'generate'])
        ->name('metadata.generate')
        ->middleware('can:metadata.generate');

    Route::get('/metadata/{federation}/download', [MetadataGenerationController::class, 'download'])
        ->name('metadata.download')
        ->middleware('can:metadata.view');

    // ── Users & Audit Log ─────────────────────────────────────────────────────
    Route::middleware('can:user.view')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'show', 'edit', 'update']);
        Route::patch('users/{user}/role',    [UserController::class, 'changeRole'])->name('users.changeRole');
        Route::patch('users/{user}/suspend', [UserController::class, 'suspend'])->name('users.suspend');
    });

    // Any authenticated user can view the audit log, scoped to own entries if not admin/operator
    Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');

    // ── Per-entity metadata operations ────────────────────────────────────────
    // GET  allows using cached result; POST forces a fresh run (bypass cache)
    Route::match(['get', 'post'], 'entities/{entity}/validate', [EntityMetadataController::class, 'validate'])
        ->name('entities.validate');

    Route::get('entities/{entity}/compliance-notify/preview', [ComplianceNotifyController::class, 'previewEntity'])
        ->name('entities.compliance-notify.preview');
    Route::post('entities/{entity}/compliance-notify', [ComplianceNotifyController::class, 'sendEntity'])
        ->name('entities.compliance-notify');

    // Raw SAML2 EntityDescriptor XML preview and download
    Route::get('entities/{entity}/metadata.xml', [EntityMetadataController::class, 'rawXml'])
        ->name('entities.metadata');
    Route::get('entities/{entity}/metadata.xml/download', [EntityMetadataController::class, 'downloadXml'])
        ->name('entities.metadata.download');

    // Per-entity certificate status (JSON)
    Route::get('entities/{entity}/certificates', [CertificateMonitoringController::class, 'entityCertificates'])
        ->name('entities.certificates');

    // ── Certificate monitoring ─────────────────────────────────────────────────
    // Dashboard — returns view or JSON depending on Accept header
    Route::get('certificates/monitor', [CertificateMonitoringController::class, 'index'])
        ->name('certificates.monitor');

    // Structured expiry report (JSON) — consumed by scheduler and external tools
    Route::get('certificates/expiry-report', [CertificateMonitoringController::class, 'expiryReport'])
        ->name('certificates.expiry-report');

    // Trigger expiry notification emails (POST — called by scheduler)
    Route::post('certificates/notify', [CertificateMonitoringController::class, 'notify'])
        ->name('certificates.notify');

    // ── Mail templates ────────────────────────────────────────────────────────
    Route::resource('mail/templates', MailTemplateController::class)
        ->names('mail.templates');
    Route::get('mail/templates/{template}/preview',
        [MailTemplateController::class, 'preview'])
        ->name('mail.templates.preview');
    Route::get('/mail/templates/{template}/content', function (\App\Models\MailTemplate $template) {
        Gate::authorize('federation.edit');
        return response()->json(['subject' => $template->subject, 'body' => $template->body]);
    })->name('mail.templates.content');

    // ── Federation mail ───────────────────────────────────────────────────────
    Route::get('/federations/{federation}/mail',
        [FederationMailController::class, 'compose'])->name('federations.mail.compose');
    Route::post('/federations/{federation}/mail',
        [FederationMailController::class, 'send'])->name('federations.mail.send');
    Route::get('/federations/{federation}/mail/log',
        [FederationMailController::class, 'mailLog'])->name('federations.mail.log');
    Route::get('/federations/{federation}/mail/preview',
        [FederationMailTemplateController::class, 'preview'])->name('federations.mail.preview');

    // ── Federation mail templates ─────────────────────────────────────────────
    Route::prefix('federations/{federation}/mail/templates')
        ->name('federations.mail.templates.')
        ->group(function () {
            Route::get('/',                        [FederationMailTemplateController::class, 'index'])->name('index');
            Route::get('/{template}/edit',         [FederationMailTemplateController::class, 'edit'])->name('edit');
            Route::post('/',                       [FederationMailTemplateController::class, 'store'])->name('store');
            Route::delete('/{template}',           [FederationMailTemplateController::class, 'destroy'])->name('destroy');
        });

    // ── Federation validators ─────────────────────────────────────────────────
    Route::prefix('federations/{federation}/validators')
        ->name('federations.validators.')
        ->group(function () {
            Route::get('/',                [FederationValidatorController::class, 'index'])->name('index');
            Route::get('/create',          [FederationValidatorController::class, 'create'])->name('create');
            Route::post('/',               [FederationValidatorController::class, 'store'])->name('store');
            Route::get('/{validator}/edit',[FederationValidatorController::class, 'edit'])->name('edit');
            Route::put('/{validator}',     [FederationValidatorController::class, 'update'])->name('update');
            Route::delete('/{validator}',  [FederationValidatorController::class, 'destroy'])->name('destroy');
            Route::post('/{validator}/run',[FederationValidatorController::class, 'runValidator'])->name('run');
        });

    // ── Registration policies ─────────────────────────────────────────────────
    Route::prefix('federations/{federation}/policies')
        ->name('federations.policies.')
        ->group(function () {
            Route::get('/',             [RegistrationPolicyController::class, 'index'])->name('index');
            Route::get('/create',       [RegistrationPolicyController::class, 'create'])->name('create');
            Route::post('/',            [RegistrationPolicyController::class, 'store'])->name('store');
            Route::get('/{policy}/edit',[RegistrationPolicyController::class, 'edit'])->name('edit');
            Route::put('/{policy}',     [RegistrationPolicyController::class, 'update'])->name('update');
            Route::delete('/{policy}',  [RegistrationPolicyController::class, 'destroy'])->name('destroy');
        });

    // ── Attribute definitions ─────────────────────────────────────────────────
    Route::resource('attributes', AttributeDefinitionController::class);

    // ── Entity requested attributes ───────────────────────────────────────────
    Route::get('/entities/{entity}/requested-attributes',
        [EntityRequestedAttributesController::class, 'index'])
        ->name('entities.requested-attributes');
    Route::post('/entities/{entity}/requested-attributes',
        [EntityRequestedAttributesController::class, 'store'])
        ->name('entities.requested-attributes.store');
    Route::delete('/entities/{entity}/requested-attributes/{attribute}',
        [EntityRequestedAttributesController::class, 'destroy'])
        ->name('entities.requested-attributes.destroy');

    // ── ARP (Attribute Release Policy) ────────────────────────────────────────
    Route::get('/entities/{entity}/arp',
        [ArpController::class, 'index'])
        ->name('entities.arp');
    Route::post('/entities/{entity}/arp',
        [ArpController::class, 'store'])
        ->name('entities.arp.store');
    Route::delete('/entities/{entity}/arp/{arp}',
        [ArpController::class, 'destroy'])
        ->name('entities.arp.destroy');

    // ── eduGAIN integration ───────────────────────────────────────────────────
    Route::prefix('edugain')->name('edugain.')->group(function () {
        Route::get('/entity/{entity}',
            [EduGainController::class, 'entityStatus'])->name('entity.status');
        Route::get('/federation',
            [EduGainController::class, 'federationStatus'])->name('federation.status');
    });

    // ── Compliance rules (global) ─────────────────────────────────────────────
    Route::get('/rules',                [RuleDefinitionController::class, 'index'])->name('rules.index');
    Route::post('/rules/{rule}/toggle', [RuleDefinitionController::class, 'toggle'])->name('rules.toggle');
    Route::post('/rules/sync',          [RuleDefinitionController::class, 'sync'])->name('rules.sync');

    // ── Federation compliance rule config ─────────────────────────────────────
    Route::prefix('federations/{federation}/rules')
        ->name('federations.rules.')
        ->group(function () {
            Route::get('/',         [FederationRuleConfigController::class, 'index'])->name('index');
            Route::patch('/{rule}', [FederationRuleConfigController::class, 'update'])->name('update');
            Route::delete('/{rule}',[FederationRuleConfigController::class, 'destroy'])->name('destroy');
        });

    // ── Entity compliance rule config ─────────────────────────────────────────
    Route::prefix('entities/{entity}/rules')
        ->name('entities.rules.')
        ->group(function () {
            Route::get('/',         [EntityRuleConfigController::class, 'index'])->name('index');
            Route::patch('/{rule}', [EntityRuleConfigController::class, 'update'])->name('update');
            Route::delete('/{rule}',[EntityRuleConfigController::class, 'destroy'])->name('destroy');
        });

    // ── Invitations & Invitation Requests ─────────────────────────────────────
    // invitation.manage (FM/Admin) + invitation.view (EM) — controller branches internally
    Route::get('/invitations',                      [InvitationController::class, 'index'])->name('invitations.index');
    Route::post('/invitations',                     [InvitationController::class, 'store'])->name('invitations.store');
    Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('invitations.resend');
    Route::delete('/invitations/{invitation}',      [InvitationController::class, 'revoke'])->name('invitations.revoke');

    // invitation.manage only
    Route::middleware('can:invitation.manage')->group(function () {
        Route::post('/invitations/{invitation}/reissue',          [InvitationController::class, 'reissue'])->name('invitations.reissue');
        Route::patch('/invitation-requests/{invRequest}/approve', [InvitationRequestController::class, 'approve'])->name('invitation-requests.approve');
        Route::patch('/invitation-requests/{invRequest}/reject',  [InvitationRequestController::class, 'reject'])->name('invitation-requests.reject');
    });

    // Accessible to both FM (invitation.manage) and EM (invitation.view); controller handles branching
    Route::get('/invitation-requests', [InvitationRequestController::class, 'index'])
        ->name('invitation-requests.index');

    Route::middleware('can:entity.requestContactInvitation')
        ->post('/entities/{entity}/invitation-requests', [InvitationRequestController::class, 'store'])
        ->name('invitation-requests.store');

    Route::middleware('can:entity.requestContactInvitation')
        ->delete('/invitation-requests/{invRequest}', [InvitationRequestController::class, 'cancel'])
        ->name('invitation-requests.cancel');

    // ── Webhooks ──────────────────────────────────────────────────────────────
    Route::resource('webhooks', WebhookController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
    Route::post('webhooks/{webhook}/deliveries/{delivery}/retry',
        [WebhookController::class, 'retryDelivery'])->name('webhooks.deliveries.retry');

    // ── Statistics & Reports ──────────────────────────────────────────────────
    Route::get('/statistics',                        [StatisticsController::class, 'index'])->name('statistics.index');
    Route::get('/statistics/export/entities',        [StatisticsController::class, 'exportEntities'])->name('statistics.export.entities');
    Route::get('/statistics/export/certificates',    [StatisticsController::class, 'exportCertificates'])->name('statistics.export.certificates');
    Route::get('/statistics/export/memberships',     [StatisticsController::class, 'exportMemberships'])->name('statistics.export.memberships');

    // ── Notifications ─────────────────────────────────────────────────────────
    Route::get('/notifications',          [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/archive',  [NotificationController::class, 'archive'])->name('notifications.archive');
    Route::get('/notifications/unread',   [NotificationController::class, 'unread'])->name('notifications.unread');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::delete('/notifications/{notification}', [NotificationController::class, 'archiveNotification'])->name('notifications.destroy');

    // ── Notification preferences ──────────────────────────────────────────────
    Route::get('/profile/notifications',  [NotificationPreferenceController::class, 'index'])->name('profile.notifications.index');
    Route::post('/profile/notifications', [NotificationPreferenceController::class, 'update'])->name('profile.notifications.update');

    // ── System preferences ────────────────────────────────────────────────────
    Route::get('/preferences',  [SystemPreferencesController::class, 'index'])->name('preferences.index');
    Route::post('/preferences', [SystemPreferencesController::class, 'update'])->name('preferences.update');

    // ── System Health UI (admin-only, feature-flagged) ────────────────────────
    Route::get('/health-status', [HealthUiController::class, 'index'])
        ->middleware('can:federation.create')
        ->name('health.ui');
    Route::get('/health-status/check/{name}', [HealthUiController::class, 'check'])
        ->middleware('can:federation.create')
        ->name('health.ui.check');

    // ── Import from Jagger (admin-only, feature-flagged) ─────────────────────
    Route::prefix('import')->name('import.')->middleware('can:federation.create')->group(function () {
        Route::get('/jagger',       [JaggerImportController::class, 'index'])->name('jagger');
        Route::post('/jagger/test', [JaggerImportController::class, 'test'])->name('jagger.test');
        Route::post('/jagger',      [JaggerImportController::class, 'run'])->name('jagger.run');
    });

    // ── Scheduler settings ────────────────────────────────────────────────────
    Route::prefix('scheduler')->name('scheduler.')->group(function () {
        Route::get('/', [SchedulerController::class, 'index'])->name('index');
        Route::post('/update', [SchedulerController::class, 'update'])->name('update');
        Route::post('/run/{job}', [SchedulerController::class, 'runNow'])->name('run');
    });
});

// ── Public (unauthenticated) metadata feed endpoints ──────────────────────────
// Consumed by eduGAIN and remote federations; no session or CSRF required.
Route::get('/metadata/{federation}/feed', [MetadataGenerationController::class, 'feed'])
    ->name('metadata.feed');
Route::get('/metadata/{federation}/edugain', [MetadataGenerationController::class, 'eduGainFeed'])
    ->name('metadata.edugain');

// ── Jagger-compatible legacy metadata endpoint ────────────────────────────────
// Backward-compatible URL for consumers migrated from Jagger.
// Only serves federations with jagger_compat_enabled = true.
Route::get('/signedmetadata/federation/{jaggerName}/metadata.xml',
    [MetadataGenerationController::class, 'jaggerCompatFeed'])
    ->name('metadata.jagger-compat');

// ── Health check endpoint ─────────────────────────────────────────────────────
// Optional bearer token auth via HEALTH_CHECK_TOKEN env var.
Route::get('/health', HealthController::class)->name('health');
