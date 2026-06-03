<?php

declare(strict_types=1);

namespace Database\Jagger;

use App\Models\Entity;
use App\Models\EntityAttribute;
use App\Models\EntityCertificate;
use App\Models\EntityContact;
use App\Models\EntityEndpoint;
use App\Models\EntityUiInfo;
use App\Models\Federation;
use App\Services\Entity\CertificateService;
use Illuminate\Support\Facades\Log;
use PDO;
use PDOException;
use Throwable;

/**
 * JaggerImporter
 *
 * Migrates data from an existing Jagger (HEAnet ResourceRegistry3) MySQL
 * database into the new Federation Manager schema.
 *
 * Usage:
 *   $importer = new JaggerImporter(dryRun: false);
 *   $importer->connectToJagger(['host' => ..., 'database' => ..., ...]);
 *   $counts = $importer->run();
 *
 * Dry-run mode:
 *   Pass dryRun: true to log what would be imported without writing anything.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * ASSUMED JAGGER SCHEMA (adjust $schema property if your installation differs)
 * ─────────────────────────────────────────────────────────────────────────
 * Tables:
 *   federation            — one row per federation
 *   sp                    — service providers (or combined entity table)
 *   idp                   — identity providers
 *   certificate           — X.509 PEM certificates
 *   endpoint              — SSO / ACS / SLO endpoints
 *   contact               — contact persons
 *   attribute             — entity categories & assurance profiles
 *   entity_in_federation  — membership pivot (entity ↔ federation)
 *
 * Customise by overriding $schema['tables'] before calling run().
 */
class JaggerImporter
{
    private PDO $jaggerDb;
    private bool $dryRun;
    private CertificateService $certService;

    /** Running totals for the summary report. */
    private array $counts = [
        'federations'  => 0,
        'entities'     => 0,
        'certificates' => 0,
        'endpoints'    => 0,
        'contacts'     => 0,
        'ui_info'      => 0,
        'attributes'   => 0,
        'memberships'  => 0,
        'skipped'      => 0,
        'errors'       => 0,
    ];

    /**
     * Cross-import ID maps so child records can resolve new UUIDs.
     *
     * jaggerEntityIdMap  — "{type}:{jaggerId}"  → new Entity UUID
     * jaggerFedIdMap     — jaggerFederationId   → new Federation UUID
     */
    private array $jaggerEntityIdMap = [];
    private array $jaggerFedIdMap    = [];

    // ─────────────────────────────────────────────────────────────────────
    // Schema configuration — override table/column names if needed
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Maps Jagger table and column names to their expected values.
     * Change keys here to match your specific Jagger installation.
     */
    protected array $schema = [
        // ── Table names ───────────────────────────────────────────────────
        'tables' => [
            'federation'  => 'federation',
            'sp'          => 'sp',
            'idp'         => 'idp',
            'certificate' => 'certificate',
            'endpoint'    => 'endpoint',
            'contact'     => 'contact',
            'attribute'   => 'attribute',           // entity categories / assurance profiles
            'membership'  => 'entity_in_federation', // pivot: alternatives — 'membership', 'member_of'
        ],

        // ── Federation columns ────────────────────────────────────────────
        'federation' => [
            'id'           => 'id',
            'uri'          => 'entity_id',    // the federation's entityID URI (registration authority)
            'name'         => 'name',
            'description'  => 'description',
            'metadata_url' => 'metadata_url',
            'status'       => 'status',       // 'active'/'inactive' or 1/0
        ],

        // ── SP columns ────────────────────────────────────────────────────
        'sp' => [
            'id'                     => 'id',
            'entity_id'              => 'entityid',
            'name_en'                => 'name',
            'description_en'         => 'description',
            'org_name'               => 'org_name',
            'org_display_name'       => 'org_display_name',
            'org_url'                => 'org_url',
            'logo_url'               => 'logo_url',
            'information_url'        => 'information_url',
            'privacy_url'            => 'privacy_url',
            'registration_authority' => 'registration_authority',
            'authn_requests_signed'  => 'authnrequest_signed',
            'want_assertions_signed' => 'want_assertions_signed',
            'status'                 => 'status',
            'edugain'                => 'edugain',
            'nameid_format'          => 'nameid_format', // newline-delimited or JSON
        ],

        // ── IdP columns ───────────────────────────────────────────────────
        'idp' => [
            'id'                     => 'id',
            'entity_id'              => 'entityid',
            'name_en'                => 'name',
            'description_en'         => 'description',
            'org_name'               => 'org_name',
            'org_display_name'       => 'org_display_name',
            'org_url'                => 'org_url',
            'logo_url'               => 'logo_url',
            'information_url'        => 'information_url',
            'privacy_url'            => 'privacy_url',
            'registration_authority' => 'registration_authority',
            'scope'                  => 'scope',
            'status'                 => 'status',
            'edugain'                => 'edugain',
            'nameid_format'          => 'nameid_format',
        ],

        // ── Certificate columns ───────────────────────────────────────────
        'certificate' => [
            'id'          => 'id',
            'entity_id'   => 'entity_id',    // FK into sp or idp table
            'entity_type' => 'entity_type',  // 'sp' or 'idp'
            'use'         => 'use',           // 'signing'/'encryption'/'both'
            'pem'         => 'pem',           // raw PEM or bare base64 block
            'active'      => 'active',        // boolean/tinyint — skip inactive certs
        ],

        // ── Endpoint columns ──────────────────────────────────────────────
        'endpoint' => [
            'id'                => 'id',
            'entity_id'         => 'entity_id',
            'entity_type'       => 'entity_type',
            'type'              => 'type',             // 'sso'/'acs'/'slo'/'artifact'
            'binding'           => 'binding',           // full SAML2 binding URN
            'location'          => 'location',
            'response_location' => 'response_location',
            'index'             => 'index',
            'is_default'        => 'is_default',
        ],

        // ── Contact columns ───────────────────────────────────────────────
        'contact' => [
            'id'           => 'id',
            'entity_id'    => 'entity_id',
            'entity_type'  => 'entity_type',
            'contact_type' => 'contact_type',   // 'technical'/'support'/'security'/etc.
            'given_name'   => 'given_name',
            'sur_name'     => 'sur_name',
            'email'        => 'email',
            'phone'        => 'phone',
        ],

        // ── Attribute columns ─────────────────────────────────────────────
        'attribute' => [
            'id'              => 'id',
            'entity_id'       => 'entity_id',
            'entity_type'     => 'entity_type',
            'attribute_name'  => 'attribute_name',  // 'entity_category'/'assurance_profile'
            'attribute_value' => 'attribute_value',
        ],

        // ── Membership pivot columns ──────────────────────────────────────
        'membership' => [
            'entity_id'     => 'entity_id',
            'entity_type'   => 'entity_type',
            'federation_id' => 'federation_id',
            'status'        => 'status',        // 'active'/'pending'/'rejected'/'suspended'
            'approved_by'   => 'approved_by',   // may be a username string in Jagger
            'approved_at'   => 'approved_at',
        ],
    ];

    // ─────────────────────────────────────────────────────────────────────

    public function __construct(bool $dryRun = false)
    {
        $this->dryRun      = $dryRun;
        $this->certService = app(CertificateService::class);
    }

    // ══════════════════════════════════════════════════════════════════════
    // Public API
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Open a PDO connection to the Jagger MySQL database.
     *
     * @param array{host: string, port?: int, database: string, username: string, password: string} $config
     * @throws PDOException on connection failure
     */
    public function connectToJagger(array $config): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['host'],
            (int) ($config['port'] ?? 3306),
            $config['database'],
        );

        $this->jaggerDb = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        Log::info('JaggerImporter: connected to Jagger database', [
            'host'     => $config['host'],
            'database' => $config['database'],
        ]);
    }

    /**
     * Execute all import phases in dependency order.
     * Returns the final count array.
     */
    public function run(): array
    {
        $prefix = $this->dryRun ? '[DRY-RUN] ' : '';
        Log::info("{$prefix}JaggerImporter: starting import");

        $this->importFederations();
        $this->importEntities();
        $this->importCertificates();
        $this->importEndpoints();
        $this->importContacts();
        $this->importAttributes();
        $this->importEntityFederationRelationships();

        Log::info("{$prefix}JaggerImporter: import complete", $this->counts);

        return $this->counts;
    }

    // ══════════════════════════════════════════════════════════════════════
    // Phase 1 — Federations
    // ══════════════════════════════════════════════════════════════════════

    public function importFederations(): void
    {
        $table = $this->schema['tables']['federation'];
        $col   = $this->schema['federation'];

        $rows = $this->fetchAll("SELECT * FROM `{$table}`");
        Log::info("JaggerImporter: found " . count($rows) . " federations in Jagger");

        foreach ($rows as $row) {
            $uri = trim($row[$col['uri']] ?? '');

            if (empty($uri)) {
                Log::warning('JaggerImporter: federation with empty URI — skipping', ['row' => $row]);
                $this->counts['skipped']++;
                continue;
            }

            $status = $this->mapStatus($row[$col['status']] ?? 'active', ['active', 'inactive'], 'active');

            if ($this->dryRun) {
                Log::info('[DRY-RUN] Would import federation', [
                    'uri'  => $uri,
                    'name' => $row[$col['name']] ?? '—',
                ]);
                $this->jaggerFedIdMap[$row[$col['id']]] = 'dry-run-' . $row[$col['id']];
                $this->counts['federations']++;
                continue;
            }

            try {
                $federation = Federation::firstOrCreate(
                    ['uri' => $uri],
                    [
                        'name'         => $row[$col['name']] ?? $uri,
                        'description'  => $row[$col['description']] ?? null,
                        'metadata_url' => $row[$col['metadata_url']] ?? null,
                        'status'       => $status,
                    ]
                );

                $this->jaggerFedIdMap[$row[$col['id']]] = $federation->id;
                $this->counts['federations']++;

                Log::info('JaggerImporter: federation imported', ['uri' => $uri, 'uuid' => $federation->id]);

            } catch (Throwable $e) {
                Log::error('JaggerImporter: failed to import federation', [
                    'uri'   => $uri,
                    'error' => $e->getMessage(),
                ]);
                $this->counts['errors']++;
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // Phase 2 — Entities (SP + IdP)
    // ══════════════════════════════════════════════════════════════════════

    public function importEntities(): void
    {
        $this->importEntitiesOfType('sp');
        $this->importEntitiesOfType('idp');
    }

    private function importEntitiesOfType(string $type): void
    {
        $table = $this->schema['tables'][$type];
        $col   = $this->schema[$type];

        $rows = $this->fetchAll("SELECT * FROM `{$table}`");

        if ($rows === null) {
            Log::warning("JaggerImporter: table {$table} not found — skipping {$type} import");
            return;
        }

        Log::info("JaggerImporter: found " . count($rows) . " {$type} rows");

        foreach ($rows as $row) {
            $entityId = trim($row[$col['entity_id']] ?? '');

            if (empty($entityId)) {
                Log::warning("JaggerImporter: {$type} row has empty entityid — skipping", [
                    'row_id' => $row[$col['id']] ?? '?',
                ]);
                $this->counts['skipped']++;
                continue;
            }

            // Jagger status can be 1/0 or 'active'/'inactive'
            $statusRaw = (string) ($row[$col['status']] ?? '1');
            $isActive  = in_array(strtolower($statusRaw), ['active', '1', 'true', 'yes', 'enabled'], true);
            $status    = $isActive ? 'active' : 'draft';

            // NameID formats — may be newline-delimited string or JSON
            $nameidFormats = $this->parseNameidFormats($row[$col['nameid_format']] ?? null);

            // registration_authority — NOT NULL in schema, fall back to config
            $regAuthority = trim($row[$col['registration_authority']] ?? '')
                ?: (config('federation.registration_authority') ?? '');

            if ($this->dryRun) {
                Log::info("[DRY-RUN] Would import {$type}", [
                    'entity_id' => $entityId,
                    'status'    => $status,
                ]);
                $this->jaggerEntityIdMap["{$type}:{$row[$col['id']]}"] = 'dry-run-' . $row[$col['id']];
                $this->counts['entities']++;
                continue;
            }

            try {
                $data = [
                    'entity_id'              => $entityId,
                    'type'                   => $type,
                    'status'                 => $status,
                    'edugain'                => (bool) ($row[$col['edugain']] ?? false),
                    'registration_authority' => $regAuthority,
                    'nameid_formats'         => $nameidFormats,
                ];

                if ($type === 'idp') {
                    $data['scope'] = trim($row[$col['scope']] ?? '') ?: null;
                    // IdP SP booleans must still be set (schema NOT NULL with default)
                    $data['sp_want_authn_requests_signed'] = true;
                    $data['sp_want_assertions_signed']     = true;
                }

                if ($type === 'sp') {
                    $data['sp_want_authn_requests_signed'] = (bool) ($row[$col['authn_requests_signed']] ?? true);
                    $data['sp_want_assertions_signed']     = (bool) ($row[$col['want_assertions_signed']] ?? true);
                    $data['requested_attributes']          = [];
                }

                $entity = Entity::firstOrCreate(['entity_id' => $entityId], $data);

                $this->jaggerEntityIdMap["{$type}:{$row[$col['id']]}"] = $entity->id;

                // Inline UI info (stored directly on the Jagger SP/IdP row)
                $this->importInlineUiInfo($entity, $row, $col);

                $this->counts['entities']++;
                Log::info("JaggerImporter: {$type} imported", ['entity_id' => $entityId, 'uuid' => $entity->id]);

            } catch (Throwable $e) {
                Log::error("JaggerImporter: failed to import {$type}", [
                    'entity_id' => $entityId,
                    'error'     => $e->getMessage(),
                ]);
                $this->counts['errors']++;
            }
        }
    }

    /**
     * Jagger stores display name, org info, etc. directly on the SP/IdP row.
     * Map each populated field into entity_ui_info.
     */
    private function importInlineUiInfo(Entity $entity, array $row, array $col): void
    {
        $fieldMap = [
            'display_name'     => 'name_en',
            'description'      => 'description_en',
            'org_name'         => 'org_name',
            'org_display_name' => 'org_display_name',
            'org_url'          => 'org_url',
            'logo_url'         => 'logo_url',
            'information_url'  => 'information_url',
            'privacy_url'      => 'privacy_url',
        ];

        foreach ($fieldMap as $newField => $jaggerColKey) {
            $jaggerCol = $col[$jaggerColKey] ?? null;
            $value     = $jaggerCol ? trim($row[$jaggerCol] ?? '') : '';

            if (empty($value)) {
                continue;
            }

            EntityUiInfo::firstOrCreate(
                ['entity_id' => $entity->id, 'field' => $newField, 'lang' => 'en'],
                ['value' => $value]
            );
            $this->counts['ui_info']++;
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // Phase 3 — Certificates
    // ══════════════════════════════════════════════════════════════════════

    public function importCertificates(): void
    {
        $table = $this->schema['tables']['certificate'];
        $col   = $this->schema['certificate'];

        $rows = $this->fetchAll("SELECT * FROM `{$table}`");
        if ($rows === null) {
            Log::warning('JaggerImporter: certificate table not found — skipping');
            return;
        }

        Log::info('JaggerImporter: found ' . count($rows) . ' certificates');

        foreach ($rows as $row) {
            // Skip inactive / disabled certs
            if (isset($row[$col['active']]) && !$row[$col['active']]) {
                $this->counts['skipped']++;
                continue;
            }

            $jaggerType = strtolower($row[$col['entity_type']] ?? 'sp');
            $mapKey     = "{$jaggerType}:{$row[$col['entity_id']]}";
            $entityUuid = $this->jaggerEntityIdMap[$mapKey] ?? null;

            if (!$entityUuid) {
                Log::warning('JaggerImporter: certificate has no matching entity — skipping', ['key' => $mapKey]);
                $this->counts['skipped']++;
                continue;
            }

            $pem = $this->normalizePem($row[$col['pem']] ?? '');

            if (empty($pem)) {
                Log::warning('JaggerImporter: empty PEM — skipping', ['key' => $mapKey]);
                $this->counts['skipped']++;
                continue;
            }

            $certUse = strtolower($row[$col['use']] ?? 'signing');
            if (!in_array($certUse, ['signing', 'encryption', 'both'], true)) {
                $certUse = 'signing';
            }

            if ($this->dryRun) {
                Log::info('[DRY-RUN] Would import certificate', ['entity' => $mapKey, 'use' => $certUse]);
                $this->counts['certificates']++;
                continue;
            }

            try {
                $parsed = $this->certService->parse($pem);

                EntityCertificate::firstOrCreate(
                    [
                        'entity_id'   => $entityUuid,
                        'fingerprint' => $parsed['fingerprint'],
                    ],
                    [
                        'use'                 => $certUse,
                        'pem'                 => $pem,
                        'subject'             => $parsed['subject'],
                        'issuer'              => $parsed['issuer'],
                        'serial'              => $parsed['serial'],
                        'not_before'          => $parsed['not_before'],
                        'not_after'           => $parsed['not_after'],
                        'key_bits'            => $parsed['key_bits'],
                        'key_algorithm'       => $parsed['key_algorithm'],
                        'signature_algorithm' => $parsed['signature_algorithm'],
                        'debian_weak'         => false,
                    ]
                );

                $this->counts['certificates']++;

            } catch (Throwable $e) {
                Log::error('JaggerImporter: certificate parse failed', [
                    'entity' => $mapKey,
                    'error'  => $e->getMessage(),
                ]);
                $this->counts['errors']++;
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // Phase 4 — Endpoints
    // ══════════════════════════════════════════════════════════════════════

    public function importEndpoints(): void
    {
        $table = $this->schema['tables']['endpoint'];
        $col   = $this->schema['endpoint'];

        $rows = $this->fetchAll("SELECT * FROM `{$table}`");
        if ($rows === null) {
            Log::warning('JaggerImporter: endpoint table not found — skipping');
            return;
        }

        Log::info('JaggerImporter: found ' . count($rows) . ' endpoints');

        foreach ($rows as $row) {
            $jaggerType = strtolower($row[$col['entity_type']] ?? 'sp');
            $mapKey     = "{$jaggerType}:{$row[$col['entity_id']]}";
            $entityUuid = $this->jaggerEntityIdMap[$mapKey] ?? null;

            if (!$entityUuid) {
                $this->counts['skipped']++;
                continue;
            }

            $endpointType = strtolower($row[$col['type']] ?? 'sso');
            if (!in_array($endpointType, ['sso', 'acs', 'slo', 'artifact'], true)) {
                $endpointType = 'sso';
            }

            if ($this->dryRun) {
                Log::info('[DRY-RUN] Would import endpoint', [
                    'entity'   => $mapKey,
                    'type'     => $endpointType,
                    'location' => $row[$col['location']] ?? '—',
                ]);
                $this->counts['endpoints']++;
                continue;
            }

            try {
                EntityEndpoint::firstOrCreate(
                    [
                        'entity_id' => $entityUuid,
                        'type'      => $endpointType,
                        'binding'   => $row[$col['binding']] ?? '',
                        'location'  => $row[$col['location']] ?? '',
                    ],
                    [
                        'response_location' => $row[$col['response_location']] ?? null,
                        'index'             => isset($row[$col['index']]) ? (int) $row[$col['index']] : null,
                        'is_default'        => (bool) ($row[$col['is_default']] ?? false),
                    ]
                );
                $this->counts['endpoints']++;

            } catch (Throwable $e) {
                Log::error('JaggerImporter: endpoint import failed', [
                    'entity' => $mapKey,
                    'error'  => $e->getMessage(),
                ]);
                $this->counts['errors']++;
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // Phase 5 — Contacts
    // ══════════════════════════════════════════════════════════════════════

    public function importContacts(): void
    {
        $table = $this->schema['tables']['contact'];
        $col   = $this->schema['contact'];

        $rows = $this->fetchAll("SELECT * FROM `{$table}`");
        if ($rows === null) {
            Log::warning('JaggerImporter: contact table not found — skipping');
            return;
        }

        Log::info('JaggerImporter: found ' . count($rows) . ' contacts');

        $validTypes = ['technical', 'support', 'security', 'administrative', 'billing'];

        foreach ($rows as $row) {
            $jaggerType = strtolower($row[$col['entity_type']] ?? 'sp');
            $entityUuid = $this->jaggerEntityIdMap["{$jaggerType}:{$row[$col['entity_id']]}"] ?? null;

            if (!$entityUuid) {
                $this->counts['skipped']++;
                continue;
            }

            $contactType = strtolower($row[$col['contact_type']] ?? 'technical');
            if (!in_array($contactType, $validTypes, true)) {
                $contactType = 'technical';
            }

            $email = trim($row[$col['email']] ?? '');
            if (empty($email)) {
                $this->counts['skipped']++;
                continue;
            }

            if ($this->dryRun) {
                Log::info('[DRY-RUN] Would import contact', [
                    'entity' => "{$jaggerType}:{$row[$col['entity_id']]}",
                    'type'   => $contactType,
                    'email'  => $email,
                ]);
                $this->counts['contacts']++;
                continue;
            }

            try {
                // EntityContact uses 'type' (not 'contact_type') for the column name
                EntityContact::firstOrCreate(
                    [
                        'entity_id' => $entityUuid,
                        'type'      => $contactType,
                        'email'     => $email,
                    ],
                    [
                        'given_name' => $row[$col['given_name']] ?? null,
                        'sur_name'   => $row[$col['sur_name']]   ?? null,
                        'phone'      => $row[$col['phone']]      ?? null,
                    ]
                );
                $this->counts['contacts']++;

            } catch (Throwable $e) {
                Log::error('JaggerImporter: contact import failed', ['error' => $e->getMessage()]);
                $this->counts['errors']++;
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // Phase 6 — Entity attributes (categories + assurance profiles)
    // ══════════════════════════════════════════════════════════════════════

    public function importAttributes(): void
    {
        $table = $this->schema['tables']['attribute'];
        $col   = $this->schema['attribute'];

        $rows = $this->fetchAll("SELECT * FROM `{$table}`");
        if ($rows === null) {
            Log::warning('JaggerImporter: attribute table not found — skipping');
            return;
        }

        Log::info('JaggerImporter: found ' . count($rows) . ' attributes');

        foreach ($rows as $row) {
            $jaggerType = strtolower($row[$col['entity_type']] ?? 'sp');
            $entityUuid = $this->jaggerEntityIdMap["{$jaggerType}:{$row[$col['entity_id']]}"] ?? null;

            if (!$entityUuid) {
                $this->counts['skipped']++;
                continue;
            }

            // Normalise attribute name to our schema enum values
            $attrName = $this->mapAttributeName($row[$col['attribute_name']] ?? null);
            if (!$attrName) {
                Log::debug('JaggerImporter: unrecognised attribute name — skipping', [
                    'raw' => $row[$col['attribute_name']] ?? '?',
                ]);
                $this->counts['skipped']++;
                continue;
            }

            $attrValue = trim($row[$col['attribute_value']] ?? '');
            if (empty($attrValue)) {
                $this->counts['skipped']++;
                continue;
            }

            if ($this->dryRun) {
                Log::info('[DRY-RUN] Would import attribute', [
                    'entity' => "{$jaggerType}:{$row[$col['entity_id']]}",
                    'name'   => $attrName,
                    'value'  => $attrValue,
                ]);
                $this->counts['attributes']++;
                continue;
            }

            try {
                EntityAttribute::firstOrCreate([
                    'entity_id'       => $entityUuid,
                    'attribute_name'  => $attrName,
                    'attribute_value' => $attrValue,
                ]);
                $this->counts['attributes']++;

            } catch (Throwable $e) {
                Log::error('JaggerImporter: attribute import failed', ['error' => $e->getMessage()]);
                $this->counts['errors']++;
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // Phase 7 — Entity-federation memberships
    // ══════════════════════════════════════════════════════════════════════

    public function importEntityFederationRelationships(): void
    {
        $table = $this->schema['tables']['membership'];
        $col   = $this->schema['membership'];

        $rows = $this->fetchAll("SELECT * FROM `{$table}`");
        if ($rows === null) {
            Log::warning('JaggerImporter: membership table not found — skipping');
            return;
        }

        Log::info('JaggerImporter: found ' . count($rows) . ' membership records');

        $validStatuses = ['active', 'pending', 'rejected', 'suspended'];

        foreach ($rows as $row) {
            $jaggerType    = strtolower($row[$col['entity_type']] ?? 'sp');
            $entityUuid    = $this->jaggerEntityIdMap["{$jaggerType}:{$row[$col['entity_id']]}"] ?? null;
            $fedUuid       = $this->jaggerFedIdMap[$row[$col['federation_id']]] ?? null;

            if (!$entityUuid || !$fedUuid) {
                Log::warning('JaggerImporter: cannot resolve membership IDs', [
                    'entity_key' => "{$jaggerType}:{$row[$col['entity_id']]}",
                    'fed_id'     => $row[$col['federation_id']],
                ]);
                $this->counts['skipped']++;
                continue;
            }

            $status = $this->mapStatus($row[$col['status']] ?? 'active', $validStatuses, 'active');

            if ($this->dryRun) {
                Log::info('[DRY-RUN] Would attach entity to federation', [
                    'entity'     => "{$jaggerType}:{$row[$col['entity_id']]}",
                    'federation' => $row[$col['federation_id']],
                    'status'     => $status,
                ]);
                $this->counts['memberships']++;
                continue;
            }

            try {
                $entity = Entity::find($entityUuid);
                if (!$entity) {
                    $this->counts['skipped']++;
                    continue;
                }

                // Approved_at may be a datetime string from Jagger
                $approvedAt = $row[$col['approved_at']] ?? null;
                $approvedAt = $approvedAt ? date('Y-m-d H:i:s', strtotime((string) $approvedAt)) : null;

                if (!$entity->federations()->where('federations.id', $fedUuid)->exists()) {
                    $entity->federations()->attach($fedUuid, [
                        'status'      => $status,
                        'approved_by' => null, // Jagger stores username string; no UUID mapping available
                        'approved_at' => $approvedAt,
                    ]);
                } else {
                    $entity->federations()->updateExistingPivot($fedUuid, [
                        'status'     => $status,
                        'approved_at' => $approvedAt,
                    ]);
                }

                $this->counts['memberships']++;

            } catch (Throwable $e) {
                Log::error('JaggerImporter: membership import failed', [
                    'entity_uuid' => $entityUuid,
                    'fed_uuid'    => $fedUuid,
                    'error'       => $e->getMessage(),
                ]);
                $this->counts['errors']++;
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════════
    // Accessors
    // ══════════════════════════════════════════════════════════════════════

    public function getCounts(): array
    {
        return $this->counts;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }

    // ══════════════════════════════════════════════════════════════════════
    // Private helpers
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Execute a query against the Jagger DB.
     * Returns null if the table doesn't exist (catches PDOException), otherwise fetchAll result.
     */
    private function fetchAll(string $sql): ?array
    {
        try {
            return $this->jaggerDb->query($sql)->fetchAll();
        } catch (PDOException $e) {
            // Table doesn't exist or other DB error — treat as absent
            return null;
        }
    }

    /**
     * Wrap a bare base64 block in PEM headers.
     * Jagger may store certs as raw base64 without the BEGIN/END lines.
     */
    private function normalizePem(string $raw): string
    {
        $raw = trim($raw);
        if (empty($raw)) {
            return '';
        }

        if (str_contains($raw, '-----BEGIN')) {
            return $raw;
        }

        // Strip any whitespace inside a bare base64 block
        $clean = preg_replace('/\s+/', '', $raw);

        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split($clean, 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }

    /**
     * Parse a Jagger NameID format value into an array.
     * Jagger may store multiple formats as newline-delimited text or JSON.
     */
    private function parseNameidFormats(mixed $raw): array
    {
        if (empty($raw)) {
            return [];
        }

        if (is_array($raw)) {
            return array_values(array_filter($raw));
        }

        // Try JSON first
        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter($decoded));
        }

        // Fall back to newline-delimited string
        return array_values(array_filter(array_map('trim', explode("\n", (string) $raw))));
    }

    /**
     * Map a raw Jagger status value to one of the allowed values.
     */
    private function mapStatus(mixed $raw, array $allowed, string $default): string
    {
        $normalized = strtolower(trim((string) $raw));

        // Handle numeric booleans (1 = active, 0 = inactive/draft)
        if ($normalized === '1') {
            return in_array('active', $allowed, true) ? 'active' : $default;
        }
        if ($normalized === '0') {
            return $default;
        }

        return in_array($normalized, $allowed, true) ? $normalized : $default;
    }

    /**
     * Normalise a Jagger attribute name to our schema enum values.
     */
    private function mapAttributeName(?string $raw): ?string
    {
        if (empty($raw)) {
            return null;
        }

        return match(strtolower(trim($raw))) {
            'entity_category',
            'entitycategory',
            'entity-category'
                => EntityAttribute::ATTR_ENTITY_CATEGORY,

            'assurance_profile',
            'assuranceprofile',
            'assurance-certification',
            'assurance_certification'
                => EntityAttribute::ATTR_ASSURANCE_PROFILE,

            default => null,
        };
    }
}
