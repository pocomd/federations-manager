<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\EntityAttribute;
use App\Exceptions\XmlSigningException;
use App\Services\Entity\EntityMetadataService;
use App\Services\Metadata\RuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * EntityMetadataController
 *
 * Handles metadata validation and the MDQ (Metadata Query) endpoint.
 *
 * Validation checks are ordered by severity and drawn from:
 *
 * SCHEMA / STRUCTURAL checks (SAML2 metadata spec):
 *   [S1]  entityID present and is a valid URI                          REQUIRED
 *   [S2]  entityID uniqueness across the registry                      REQUIRED
 *   [S3]  At least one role descriptor (IDPSSODescriptor / SPSSODescriptor) REQUIRED
 *   [S4]  protocolSupportEnumeration maps to a valid SAML2 protocol    REQUIRED
 *   [S5]  At least one X.509 certificate (KeyDescriptor)               REQUIRED
 *   [S6]  At least one SSO endpoint for IdP                            REQUIRED
 *   [S7]  At least one ACS endpoint for SP                             REQUIRED
 *   [S8]  ACS endpoint index values are unique (SP only)               REQUIRED
 *   [S9]  All endpoint binding URIs are valid SAML2 identifiers        REQUIRED
 *   [S10] Endpoint Location values are HTTPS URLs                      REQUIRED
 *
 * CERTIFICATE checks:
 *   [C1]  Certificate is valid X.509 PEM                               REQUIRED
 *   [C2]  Certificate key size ≥ 2048 bits (RSA) or ≥ 256 bits (EC)   REQUIRED
 *   [C3]  Certificate is not expired (warn if < 30 days remaining)     REQUIRED
 *   [C4]  Certificate is not a Debian weak key (CVE-2008-0166)         REQUIRED
 *   [C5]  Certificate uses SHA-256 or stronger signature algorithm     RECOMMENDED
 *
 * REFEDS / eduGAIN compliance checks:
 *   [R1]  mdui:DisplayName in English present                          RECOMMENDED (eduGAIN)
 *   [R2]  mdui:Description in English present                          RECOMMENDED (eduGAIN)
 *   [R3]  md:OrganizationName in English present                       RECOMMENDED (eduGAIN)
 *   [R4]  md:OrganizationDisplayName in English present                RECOMMENDED (eduGAIN)
 *   [R5]  md:OrganizationURL in English present                        RECOMMENDED (eduGAIN)
 *   [R6]  At least one md:ContactPerson (technical or support)         RECOMMENDED (eduGAIN)
 *   [R7]  SIRTFI: security contact present when sirtfi asserted        REQUIRED for SIRTFI
 *   [R8]  CoCo: privacy statement URL present when CoCo asserted       REQUIRED for CoCo v2
 *   [R9]  R&S: entity category URI is the canonical REFEDS URI         REQUIRED for R&S
 *   [R10] shibmd:Scope present for IdP                                 RECOMMENDED
 *   [R11] Scope matches entityID domain                                RECOMMENDED
 *   [R12] mdrpi:RegistrationInfo present with registrationAuthority    REQUIRED for eduGAIN
 *   [R13] SP WantAssertionsSigned=true                                 RECOMMENDED (security)
 *   [R14] SP AuthnRequestsSigned=true                                  RECOMMENDED (security)
 *   [R15] NameIDFormat does not include unspecified without reason      WARNING
 */
class EntityMetadataController extends Controller
{
    // SAML2 binding URIs
    public const BINDING_HTTP_POST     = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST';
    public const BINDING_HTTP_REDIRECT = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect';
    public const BINDING_HTTP_ARTIFACT = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact';
    public const BINDING_SOAP          = 'urn:oasis:names:tc:SAML:2.0:bindings:SOAP';
    public const BINDING_PAOS          = 'urn:oasis:names:tc:SAML:2.0:bindings:PAOS';
    public const BINDING_SIMPLESIGN    = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST-SimpleSign';

    // NameID format URIs
    public const NAMEID_TRANSIENT   = 'urn:oasis:names:tc:SAML:2.0:nameid-format:transient';
    public const NAMEID_PERSISTENT  = 'urn:oasis:names:tc:SAML:2.0:nameid-format:persistent';
    public const NAMEID_UNSPECIFIED = 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified';
    public const NAMEID_EMAIL       = 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress';

    // Protocol support
    public const SAML2_PROTOCOL = 'urn:oasis:names:tc:SAML:2.0:protocol';

    public function __construct(
        private readonly EntityMetadataService $metadataService,
        private readonly RuleRegistry          $registry,
    ) {}

    /**
     * Run SAML metadata validation for a single entity.
     *
     * Dual response: JSON for API/AJAX requests (legacy format, cached 6 hours), HTML view for browsers
     * (newer engine with not_applicable support). Pass ?force=1 to bypass the JSON cache.
     */
    public function validate(Request $request, Entity $entity): Response|JsonResponse
    {
        Gate::authorize('entity.view');

        $entity->load(['certificates', 'uiInfo', 'contacts', 'endpoints', 'attributes']);

        $forceRefresh = $request->boolean('force', false);
        $cacheKey     = "metadata_validation:{$entity->id}";

        // API / AJAX request → JSON (old format, backward compatible)
        if ($request->expectsJson() || $request->is('api/*')) {
            if (! $forceRefresh && Cache::has($cacheKey)) {
                $result = Cache::get($cacheKey);
            } else {
                $result = $this->runAllChecks($entity);
                Cache::put($cacheKey, $result, now()->addHours(6));
                Log::info('Metadata validated', [
                    'entity_id' => $entity->entity_id,
                    'passed'    => $result['passed'],
                    'errors'    => count($result['errors']),
                    'warnings'  => count($result['warnings']),
                ]);
            }
            return response()->json($result);
        }

        // Browser request → HTML view (new engine format with not_applicable support)
        $validationResult = $this->metadataService->validate($entity);
        /** @var array<string, mixed> $arr */
        $arr     = $validationResult->toArray();
        /** @var array<string, mixed> $rawSummary */
        $rawSummary = $arr['summary'];
        $summary    = array_merge((array) $rawSummary, [
            'entity_id'   => $entity->entity_id,
            'entity_type' => $entity->type,
            'checked_at'  => now()->toIso8601String(),
        ]);

        // Sort: fail → warning → pass → not_applicable
        /** @var array<int, array<string, mixed>> $rawChecks */
        $rawChecks = (array) ($arr['checks'] ?? []);
        $checks    = collect($rawChecks)->sortBy(fn(array $c) => match($c['status'] ?? '') {
            'fail'           => 0,
            'warning'        => 1,
            'pass'           => 2,
            'not_applicable' => 3,
            default          => 4,
        })->values()->all();

        $descriptions = collect($this->registry->all())
            ->keyBy(fn($r) => $r->id())
            ->map(fn($r) => [
                'name'        => $r->name(),
                'description' => $r->description(),
                'spec_url'    => $r->specUrl(),
            ])
            ->all();

        return response()->view('entities.validate', [
            'entity'       => $entity,
            'result'       => array_merge($arr, $summary),
            'checks'       => $checks,
            'summary'      => $summary,
            'descriptions' => $descriptions,
        ]);
    }

    /**
     * MDQ (Metadata Query Protocol) endpoint — returns signed XML for a single active entity by SHA-1 hash.
     *
     * No authentication required. Falls back to unsigned XML (with a log warning) if xmlsectool is unavailable.
     * Response is cached for 6 hours; X-Metadata-Signed header indicates whether signing succeeded.
     *
     * @authorizes  none (public endpoint)
     */
    public function mdq(string $hash): Response
    {
        $entity = Entity::query()
            ->where('sha1_entity_id', $hash)
            ->where('status', 'active')
            ->with(['certificates', 'uiInfo', 'contacts', 'endpoints', 'attributes'])
            ->firstOrFail();

        $cacheKey = "mdq:{$entity->id}";
        $signed   = true;

        $xml = Cache::remember($cacheKey, now()->addHours(6), function () use ($entity, &$signed) {
            try {
                return $this->metadataService->renderSignedXml($entity);
            } catch (XmlSigningException $e) {
                Log::warning('MDQ: signing unavailable, serving unsigned XML', [
                    'entity_id' => $entity->entity_id,
                    'error'     => $e->getMessage(),
                ]);
                $signed = false;
                return $this->metadataService->renderXml($entity);
            }
        });

        return response($xml, 200, [
            'Content-Type'        => 'application/samlmetadata+xml',
            'Cache-Control'       => 'max-age=21600',
            'X-Metadata-Signed'   => $signed ? 'true' : 'false',
        ]);
    }

    public function rawXml(Entity $entity): Response
    {
        Gate::authorize('metadata.view');

        $xml = Cache::remember(
            EntityMetadataService::xmlCacheKey($entity),
            EntityMetadataService::XML_CACHE_TTL,
            fn() => $this->metadataService->renderXml($entity)
        );

        return response($xml, 200, [
            'Content-Type'        => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'inline; filename="' . urlencode($entity->entity_id) . '.xml"',
        ]);
    }

    public function downloadXml(Entity $entity): Response
    {
        Gate::authorize('metadata.view');

        $xml      = Cache::remember(
            EntityMetadataService::xmlCacheKey($entity),
            EntityMetadataService::XML_CACHE_TTL,
            fn() => $this->metadataService->renderXml($entity)
        );
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $entity->entity_id) . '.xml';

        return response($xml, 200, [
            'Content-Type'        => 'application/xml; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // Relations (uiInfo, contacts, endpoints, attributes, certificates) must
    // be loaded on $entity before calling this method.

    private function runAllChecks(Entity $entity): array
    {
        $checks   = [];
        $errors   = [];
        $warnings = [];

        // Pre-compute relationship projections used across multiple checks
        $entityCategories = $entity->attributes
            ->where('attribute_name', 'entity_category')
            ->pluck('attribute_value')
            ->toArray();

        $assuranceProfiles = $entity->attributes
            ->where('attribute_name', 'assurance_profile')
            ->pluck('attribute_value')
            ->toArray();

        $hasSirtfi = in_array(EntityAttribute::URI_SIRTFI, $assuranceProfiles, true);

        // [S1] entityID present and valid URI
        $this->check($checks, $errors, warnings: $warnings,
            code:    'S1',
            level:   'error',
            passed:  ! empty($entity->entity_id) && (bool) filter_var($entity->entity_id, FILTER_VALIDATE_URL),
            message: '[S1] entityID must be a valid URI',
            detail:  'entityID: ' . ($entity->entity_id ?: '(empty)'),
        );

        // [S2] entityID uniqueness
        $duplicate = Entity::where('entity_id', $entity->entity_id)
            ->where('id', '!=', $entity->id)
            ->exists();
        $this->check($checks, $errors, $warnings,
            code:    'S2',
            level:   'error',
            passed:  ! $duplicate,
            message: '[S2] entityID must be unique across the registry',
            detail:  'Duplicate found for: ' . $entity->entity_id,
        );

        // [S3] At least one role descriptor with a valid endpoint
        $hasIdpDescriptor = $entity->type === 'idp' &&
            $entity->endpoints->where('type', 'sso')->isNotEmpty();
        $hasSpDescriptor  = $entity->type === 'sp'  &&
            $entity->endpoints->where('type', 'acs')->isNotEmpty();
        $this->check($checks, $errors, $warnings,
            code:    'S3',
            level:   'error',
            passed:  $hasIdpDescriptor || $hasSpDescriptor,
            message: '[S3] At least one role descriptor with a valid endpoint is required',
            detail:  'Type: ' . $entity->type,
        );

        // [S4] protocolSupportEnumeration — valid for known entity types
        $this->check($checks, $errors, $warnings,
            code:    'S4',
            level:   'error',
            passed:  in_array($entity->type, ['idp', 'sp'], true),
            message: '[S4] protocolSupportEnumeration maps to valid SAML2 protocol',
            detail:  'Type: ' . $entity->type,
        );

        // [S5] At least one certificate (KeyDescriptor)
        $this->check($checks, $errors, $warnings,
            code:    'S5',
            level:   'error',
            passed:  $entity->certificates->isNotEmpty(),
            message: '[S5] At least one X.509 certificate (KeyDescriptor) is required',
            detail:  'Certificates found: ' . $entity->certificates->count(),
        );

        // [S6] IdP must have at least one SSO endpoint
        if ($entity->type === 'idp') {
            $ssoEndpoints = $entity->endpoints->where('type', 'sso');
            $this->check($checks, $errors, $warnings,
                code:    'S6',
                level:   'error',
                passed:  $ssoEndpoints->isNotEmpty(),
                message: '[S6] IdP must have at least one SingleSignOnService endpoint',
                detail:  'SSO endpoints: ' . $ssoEndpoints->pluck('location')->implode(', '),
            );
        }

        // [S7] SP must have at least one ACS endpoint
        if ($entity->type === 'sp') {
            $acsEndpoints = $entity->endpoints->where('type', 'acs');
            $this->check($checks, $errors, $warnings,
                code:    'S7',
                level:   'error',
                passed:  $acsEndpoints->isNotEmpty(),
                message: '[S7] SP must have at least one AssertionConsumerService endpoint',
                detail:  'ACS endpoints: ' . $acsEndpoints->pluck('location')->implode(', '),
            );
        }

        // [S8] ACS index uniqueness (SP only)
        if ($entity->type === 'sp') {
            $acsIndexes = $entity->endpoints
                ->where('type', 'acs')
                ->pluck('index')
                ->filter(fn($i) => $i !== null);
            $hasDuplicates = $acsIndexes->count() !== $acsIndexes->unique()->count();
            $this->check($checks, $errors, $warnings,
                code:    'S8',
                level:   'error',
                passed:  ! $hasDuplicates,
                message: '[S8] ACS endpoint index values must be unique',
                detail:  'Index values: ' . $acsIndexes->implode(', '),
            );
        }

        // [S9] All endpoint binding URIs must be valid SAML2 identifiers
        $validBindings = [
            self::BINDING_HTTP_POST,
            self::BINDING_HTTP_REDIRECT,
            self::BINDING_HTTP_ARTIFACT,
            self::BINDING_SOAP,
            self::BINDING_PAOS,
            self::BINDING_SIMPLESIGN,
        ];
        foreach ($entity->endpoints as $endpoint) {
            if (!in_array($endpoint->binding, $validBindings, true)) {
                $this->check($checks, $errors, $warnings,
                    code:    'S9',
                    level:   'error',
                    passed:  false,
                    message: "[S9] Invalid SAML2 binding URI: {$endpoint->binding}",
                    detail:  "Endpoint type: {$endpoint->type}, location: {$endpoint->location}",
                );
            }
        }

        // [S10] All endpoints must be HTTPS
        $allUrls  = $entity->endpoints->pluck('location');
        $nonHttps = $allUrls->filter(fn($url) => ! str_starts_with($url, 'https://'))->values();
        $this->check($checks, $errors, $warnings,
            code:    'S10',
            level:   'error',
            passed:  $nonHttps->isEmpty(),
            message: '[S10] All endpoint Location values must use HTTPS',
            detail:  'Non-HTTPS endpoints: ' . $nonHttps->implode(', '),
        );

        $certificates = $entity->certificates;

        // [C1] At least one certificate
        $this->check($checks, $errors, $warnings,
            code:    'C1',
            level:   'error',
            passed:  $certificates->isNotEmpty(),
            message: '[C1] At least one X.509 certificate (KeyDescriptor) is required',
            detail:  'Certificates found: ' . $certificates->count(),
        );

        foreach ($certificates as $cert) {
            // [C2] Key size
            $minBits = str_starts_with($cert->key_algorithm ?? '', 'EC') ? 256 : 2048;
            $this->check($checks, $errors, $warnings,
                code:    'C2',
                level:   'error',
                passed:  ($cert->key_bits ?? 0) >= $minBits,
                message: "[C2] Certificate key too small (found {$cert->key_bits} bits, minimum {$minBits} bits)",
                detail:  "Certificate subject: {$cert->subject}",
            );

            // [C3] Expiry
            $daysUntilExpiry = now()->diffInDays($cert->not_after, false);
            if ($daysUntilExpiry < 0) {
                $this->check($checks, $errors, $warnings,
                    code:    'C3',
                    level:   'error',
                    passed:  false,
                    message: "[C3] Certificate expired {$cert->not_after}",
                    detail:  "Subject: {$cert->subject}",
                );
            } elseif ($daysUntilExpiry < 30) {
                $this->check($checks, $errors, $warnings,
                    code:    'C3',
                    level:   'warning',
                    passed:  false,
                    message: "[C3] Certificate expires in {$daysUntilExpiry} days ({$cert->not_after})",
                    detail:  "Subject: {$cert->subject} — rotate before expiry",
                );
            }

            // [C4] Debian weak key
            $this->check($checks, $errors, $warnings,
                code:    'C4',
                level:   'error',
                passed:  ! $cert->debian_weak,
                message: '[C4] Debian weak key detected (CVE-2008-0166) — this certificate must be replaced',
                detail:  "Fingerprint: {$cert->fingerprint}",
            );

            // [C5] SHA-256+ signature algorithm
            $weakAlgo = in_array($cert->signature_algorithm ?? '', ['sha1WithRSAEncryption', 'md5WithRSAEncryption']);
            $this->check($checks, $errors, $warnings,
                code:    'C5',
                level:   'warning',
                passed:  ! $weakAlgo,
                message: "[C5] Certificate uses weak signature algorithm: {$cert->signature_algorithm}",
                detail:  'Use SHA-256 (sha256WithRSAEncryption) or stronger',
            );
        }

        // [R1] mdui:DisplayName in English
        $this->check($checks, $errors, $warnings,
            code:    'R1',
            level:   'warning',
            passed:  $entity->uiInfo->where('field', 'display_name')->where('lang', 'en')->isNotEmpty(),
            message: '[R1] mdui:DisplayName in English is recommended (eduGAIN requirement)',
            detail:  'Add a display_name ui_info entry with lang="en"',
        );

        // [R2] mdui:Description in English
        $this->check($checks, $errors, $warnings,
            code:    'R2',
            level:   'warning',
            passed:  $entity->uiInfo->where('field', 'description')->where('lang', 'en')->isNotEmpty(),
            message: '[R2] mdui:Description in English is recommended (eduGAIN requirement)',
            detail:  'Add a description ui_info entry with lang="en"',
        );

        // [R3] md:OrganizationName in English
        $this->check($checks, $errors, $warnings,
            code:    'R3',
            level:   'warning',
            passed:  $entity->uiInfo->where('field', 'org_name')->where('lang', 'en')->isNotEmpty(),
            message: '[R3] md:OrganizationName in English is recommended',
            detail:  'Add an org_name ui_info entry with lang="en"',
        );

        // [R4] md:OrganizationDisplayName in English
        $this->check($checks, $errors, $warnings,
            code:    'R4',
            level:   'warning',
            passed:  $entity->uiInfo->where('field', 'org_display_name')->where('lang', 'en')->isNotEmpty(),
            message: '[R4] md:OrganizationDisplayName in English is recommended',
            detail:  'Add an org_display_name ui_info entry with lang="en"',
        );

        // [R5] md:OrganizationURL in English
        $this->check($checks, $errors, $warnings,
            code:    'R5',
            level:   'warning',
            passed:  $entity->uiInfo->where('field', 'org_url')->where('lang', 'en')->isNotEmpty(),
            message: '[R5] md:OrganizationURL in English is recommended',
            detail:  'Add an org_url ui_info entry with lang="en"',
        );

        // [R6] ContactPerson (technical or support)
        $hasContact = $entity->contacts
            ->whereIn('type', ['technical', 'support'])
            ->isNotEmpty();
        $this->check($checks, $errors, $warnings,
            code:    'R6',
            level:   'warning',
            passed:  $hasContact,
            message: '[R6] At least one md:ContactPerson (technical or support) is recommended',
            detail:  'Add a contact with type="technical" or type="support"',
        );

        // [R7] SIRTFI requires security contact
        if ($hasSirtfi) {
            $this->check($checks, $errors, $warnings,
                code:    'R7',
                level:   'error',
                passed:  $entity->contacts->where('type', 'security')->isNotEmpty(),
                message: '[R7] SIRTFI asserted but no security contact (contactType="security") found',
                detail:  'REFEDS SIRTFI requires a security contact',
            );
        }

        // [R8] CoCo v2 requires privacy URL
        $assertsCoCo = in_array(EntityAttribute::URI_COCO_V2, $entityCategories, true);
        if ($assertsCoCo) {
            $this->check($checks, $errors, $warnings,
                code:    'R8',
                level:   'error',
                passed:  $entity->uiInfo->where('field', 'privacy_url')->where('lang', 'en')->isNotEmpty(),
                message: '[R8] REFEDS Code of Conduct v2 asserted but no mdui:PrivacyStatementURL found',
                detail:  'CoCo v2 requires a PrivacyStatementURL — add a privacy_url ui_info entry',
            );
        }

        // [R9] R&S entity category URI — must be the canonical REFEDS URI
        if (! empty($entityCategories)) {
            foreach ($entityCategories as $cat) {
                if (str_contains($cat, 'research-and-scholarship') && $cat !== EntityAttribute::URI_RS) {
                    $this->check($checks, $errors, $warnings,
                        code:    'R9',
                        level:   'error',
                        passed:  false,
                        message: '[R9] Non-canonical R&S URI detected — must use: ' . EntityAttribute::URI_RS,
                        detail:  "Found: {$cat}",
                    );
                }
            }
        }

        // [R10/R11] IdP scope
        if ($entity->type === 'idp') {
            $this->check($checks, $errors, $warnings,
                code:    'R10',
                level:   'warning',
                passed:  ! empty($entity->scope),
                message: '[R10] shibmd:Scope is recommended for Identity Providers',
                detail:  'Scope helps discovery services and attribute release policies',
            );

            if (! empty($entity->scope) && ! empty($entity->entity_id)) {
                $entityHost = parse_url($entity->entity_id, PHP_URL_HOST) ?? '';
                $scopeMatch = str_ends_with($entityHost, $entity->scope)
                    || str_ends_with($entity->scope, $entityHost);
                $this->check($checks, $errors, $warnings,
                    code:    'R11',
                    level:   'warning',
                    passed:  $scopeMatch,
                    message: "[R11] Scope '{$entity->scope}' does not match entityID domain '{$entityHost}'",
                    detail:  'Scope should match or be a subdomain of the entityID hostname',
                );
            }
        }

        // [R12] mdrpi:RegistrationInfo
        $this->check($checks, $errors, $warnings,
            code:    'R12',
            level:   'error',
            passed:  ! empty($entity->registration_authority),
            message: '[R12] mdrpi:RegistrationInfo with registrationAuthority is required for eduGAIN',
            detail:  'Set FEDERATION_REGISTRATION_AUTHORITY in config/federation.php',
        );

        // [R13] SP WantAssertionsSigned
        if ($entity->type === 'sp') {
            $this->check($checks, $errors, $warnings,
                code:    'R13',
                level:   'warning',
                passed:  $entity->sp_want_assertions_signed === true,
                message: '[R13] SPSSODescriptor/@WantAssertionsSigned should be true for security',
                detail:  'Current value: ' . ($entity->sp_want_assertions_signed ? 'true' : 'false'),
            );

            // [R14] SP AuthnRequestsSigned
            $this->check($checks, $errors, $warnings,
                code:    'R14',
                level:   'warning',
                passed:  $entity->sp_want_authn_requests_signed === true,
                message: '[R14] SPSSODescriptor/@AuthnRequestsSigned should be true for security',
                detail:  'Current value: ' . ($entity->sp_want_authn_requests_signed ? 'true' : 'false'),
            );
        }

        // [R15] NameIDFormat: warn about unspecified
        if (in_array(self::NAMEID_UNSPECIFIED, $entity->nameid_formats ?? [])) {
            $this->check($checks, $errors, $warnings,
                code:    'R15',
                level:   'warning',
                passed:  false,
                message: '[R15] NameIDFormat:unspecified is listed — consider using transient or persistent instead',
                detail:  'Using "unspecified" leaves the NameID format undefined; prefer explicit formats',
            );
        }

        $passed = empty($errors);

        return [
            'entity_id'   => $entity->entity_id,
            'entity_type' => $entity->type,
            'passed'      => $passed,
            'checked_at'  => now()->toIso8601String(),
            'summary'     => [
                'total'    => count($checks),
                'passed'   => count(array_filter($checks, fn($c) => $c['passed'])),
                'errors'   => count($errors),
                'warnings' => count($warnings),
            ],
            'errors'   => $errors,
            'warnings' => $warnings,
            'checks'   => $checks,
        ];
    }

    /**
     * Record a single check result into the checks/errors/warnings arrays.
     */
    private function check(
        array  &$checks,
        array  &$errors,
        array  &$warnings,
        string $code,
        string $level,
        bool   $passed,
        string $message,
        string $detail = '',
    ): void {
        $entry    = compact('code', 'level', 'passed', 'message', 'detail');
        $checks[] = $entry;

        if (! $passed) {
            if ($level === 'error') {
                $errors[]   = $entry;
            } else {
                $warnings[] = $entry;
            }
        }
    }
}
