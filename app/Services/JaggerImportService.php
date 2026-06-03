<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Imports federations, entities and related data from a legacy Jagger
 * (ResourceRegistry 3) MySQL database into the current schema.
 *
 * Field-level mapping is documented inline. Key decisions:
 *  - provider.type = 'BOTH' → imported as 'idp' (counted separately)
 *  - is_approved + is_active → draft / pending / active / suspended
 *  - federation_members.joinstate + isdisabled/isbanned → entity_federation.status
 *  - certificate.certdata (bare base64) → PEM-wrapped, parsed via openssl
 *  - Jagger PHP-serialized columns (scope, nameids, ldisplayname …) are
 *    unserialized to extract usable values and localized UI-info rows
 */
class JaggerImportService
{
    private \Illuminate\Database\Connection $jagger;

    private array $stats = [
        'federations'       => ['created' => 0, 'skipped' => 0, 'errors' => 0],
        'entities'          => ['created' => 0, 'skipped' => 0, 'errors' => 0, 'both_type' => 0],
        'memberships'       => ['created' => 0, 'skipped' => 0, 'errors' => 0],
        'certificates'      => ['created' => 0, 'skipped' => 0, 'errors' => 0],
        'contacts'          => ['created' => 0, 'skipped' => 0, 'errors' => 0],
        'endpoints'         => ['created' => 0, 'skipped' => 0, 'errors' => 0],
        'attributes'        => ['created' => 0, 'skipped' => 0, 'errors' => 0],
        'attr_requirements' => ['created' => 0, 'skipped' => 0, 'errors' => 0],
    ];

    /** Jagger integer ID → our UUID for cross-step foreign-key resolution */
    private array $fedMap    = [];
    private array $entityMap = [];
    private array $attrMap   = [];

    private array $importErrors   = [];
    private array $importWarnings = [];

    public function __construct(array $credentials)
    {
        config(['database.connections.jagger' => [
            'driver'    => 'mysql',
            'host'      => $credentials['host'],
            'port'      => (int) ($credentials['port'] ?? 3306),
            'database'  => $credentials['database'],
            'username'  => $credentials['username'],
            'password'  => $credentials['password'] ?? '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'options'   => [\PDO::ATTR_TIMEOUT => 5],
        ]]);

        DB::purge('jagger');
        $this->jagger = DB::connection('jagger');
    }

    // ── Public API ────────────────────────────────────────────────────────────

    public function testConnection(): bool
    {
        try {
            $this->jagger->select('SELECT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function preview(): array
    {
        return [
            'federations'       => $this->jagger->table('federation')->count(),
            'entities'          => $this->jagger->table('provider')->count(),
            'local_entities'    => $this->jagger->table('provider')->where('is_local', 1)->count(),
            'memberships'       => $this->jagger->table('federation_members')->count(),
            'certificates'      => $this->jagger->table('certificate')->count(),
            'contacts'          => $this->jagger->table('contact')->count(),
            'endpoints'         => $this->jagger->table('service_location')->count(),
            'attributes'        => $this->jagger->table('attribute')->count(),
            'attr_requirements' => $this->jagger->table('attribute_requirement')->count(),
        ];
    }

    public function run(bool $onlyLocal = true, bool $skipExisting = true, bool $clearFirst = false): array
    {
        try {
            DB::transaction(function () use ($onlyLocal, $skipExisting, $clearFirst): void {
                if ($clearFirst) {
                    $this->clearImportedData();
                }
                $this->importFederations($skipExisting);
                $this->importEntities($onlyLocal, $skipExisting);
                $this->importMemberships();
                $this->importCertificates();
                $this->importContacts();
                $this->importEndpoints();
                $this->importAttributes($skipExisting);
                $this->importAttributeRequirements();

                if (!empty($this->importErrors)) {
                    throw new \RuntimeException('__rollback__');
                }
            });

            return [
                'success'  => true,
                'stats'    => $this->stats,
                'errors'   => [],
                'warnings' => $this->importWarnings,
            ];
        } catch (Throwable $e) {
            $errors = $this->importErrors;
            if ($e->getMessage() !== '__rollback__') {
                $errors[] = 'Fatal: ' . $e->getMessage();
            }
            return [
                'success'  => false,
                'stats'    => $this->stats,
                'errors'   => $errors,
                'warnings' => $this->importWarnings,
            ];
        }
    }

    // ── Pre-import clear ─────────────────────────────────────────────────────

    private function clearImportedData(): void
    {
        // Resolve imported entity IDs first so child deletes are scoped correctly.
        $importedEntityIds = DB::table('entities')
            ->where('source', 'imported')
            ->pluck('id');

        // Child tables (FK → entities)
        DB::table('entity_certificates')->whereIn('entity_id', $importedEntityIds)->delete();
        DB::table('entity_contacts')->whereIn('entity_id', $importedEntityIds)->delete();
        DB::table('entity_endpoints')->whereIn('entity_id', $importedEntityIds)->delete();
        DB::table('entity_ui_info')->whereIn('entity_id', $importedEntityIds)->delete();
        DB::table('entity_attributes')->whereIn('entity_id', $importedEntityIds)->delete();
        DB::table('entity_requested_attributes')->whereIn('entity_id', $importedEntityIds)->delete();
        DB::table('entity_federation')->whereIn('entity_id', $importedEntityIds)->delete();

        // Entities tagged as imported
        DB::table('entities')->where('source', 'imported')->delete();

        // Federations (no source marker — all are imported; manually-created ones
        // would need a different source tag which doesn't exist yet)
        $fedIds = DB::table('federations')->pluck('id');
        DB::table('federation_required_attributes')->whereIn('federation_id', $fedIds)->delete();
        DB::table('federations')->delete();
    }

    // ── Step 1: Federations ───────────────────────────────────────────────────

    private function importFederations(bool $skipExisting): void
    {
        foreach ($this->jagger->table('federation')->get() as $row) {
            try {
                $existing = DB::table('federations')->where('uri', $row->urn)->first();

                if ($existing) {
                    $this->fedMap[$row->id] = $existing->id;
                    $this->stats['federations']['skipped']++;
                    continue;
                }

                $id   = Str::uuid()->toString();
                $slug = $this->uniqueSlug($row->name);

                DB::table('federations')->insert([
                    'id'          => $id,
                    'name'        => $row->name,
                    'slug'        => $slug,
                    'uri'         => $row->urn,
                    'description' => $row->description ?? null,
                    'status'      => $row->is_active ? 'active' : 'inactive',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                $this->fedMap[$row->id] = $id;
                $this->stats['federations']['created']++;
            } catch (Throwable $e) {
                $this->stats['federations']['errors']++;
                $this->importErrors[] = "Federation [{$row->name}]: {$e->getMessage()}";
            }
        }
    }

    // ── Step 2: Entities (providers) ─────────────────────────────────────────

    private function importEntities(bool $onlyLocal, bool $skipExisting): void
    {
        $query = $this->jagger->table('provider');
        if ($onlyLocal) {
            $query->where('is_local', 1);
        }

        foreach ($query->get() as $row) {
            try {
                $existing = DB::table('entities')
                    ->where('entity_id', $row->entityid)
                    ->whereNull('deleted_at')
                    ->first();

                if ($existing) {
                    $this->entityMap[$row->id] = $existing->id;
                    $this->stats['entities']['skipped']++;
                    continue;
                }

                $type = strtolower((string) ($row->type ?? 'idp'));
                if ($type === 'both') {
                    $type = 'idp';
                    $this->stats['entities']['both_type']++;
                }
                if (!in_array($type, ['idp', 'sp', 'oidc'], true)) {
                    $type = 'idp';
                }

                $scope = null;
                if (!empty($row->scope)) {
                    $scopes = $this->phpUnserialize($row->scope);
                    if (is_array($scopes) && count($scopes) > 0) {
                        $val = array_key_exists('', $scopes) ? $scopes[''] : reset($scopes);
                        // Jagger sometimes nests arrays one level deep
                        $scope = is_array($val) ? (reset($val) ?: null) : $val;
                        $scope = is_string($scope) && $scope !== '' ? $scope : null;
                    } elseif (is_string($scopes) && $scopes !== '') {
                        $scope = $scopes;
                    }
                }

                $nameidFormats = [];
                if (!empty($row->nameids)) {
                    $raw = $this->phpUnserialize($row->nameids);
                    if (is_array($raw)) {
                        // Flatten one level — Jagger occasionally stores arrays-of-arrays
                        $flat = [];
                        foreach ($raw as $item) {
                            is_array($item) ? array_push($flat, ...array_values($item)) : ($flat[] = $item);
                        }
                        $nameidFormats = array_values(array_filter($flat, 'is_string'));
                    }
                }

                $id = Str::uuid()->toString();

                DB::table('entities')->insert([
                    'id'                            => $id,
                    'entity_id'                     => $row->entityid,
                    'type'                          => $type,
                    'status'                        => $this->mapEntityStatus(
                                                          (bool) $row->is_approved,
                                                          (bool) $row->is_active
                                                      ),
                    'scope'                         => $scope,
                    'nameid_formats'                => $nameidFormats ? json_encode($nameidFormats) : null,
                    'sp_want_authn_requests_signed' => (int) (bool) ($row->wantauthnreqsigned ?? false),
                    'sp_want_assertions_signed'     => (int) (bool) ($row->wantassertsigned ?? false),
                    'registration_authority'        => $row->registrar
                                                        ?? $this->fedRegistrar($row->federation_id ?? null)
                                                        ?? config('federation.registration_authority')
                                                        ?? '',
                    'edugain'                       => 0,
                    'requested_attributes'          => null,
                    'sha1_entity_id'                => sha1($row->entityid),
                    'source'                        => 'imported',
                    'created_at'                    => $row->created_at ?? now(),
                    'updated_at'                    => $row->updated_at ?? now(),
                    'deleted_at'                    => null,
                ]);

                $this->entityMap[$row->id] = $id;
                $this->importEntityUiInfo($id, $row);
                $this->stats['entities']['created']++;
            } catch (Throwable $e) {
                $this->stats['entities']['errors']++;
                $this->importErrors[] = "Entity [{$row->entityid}]: {$e->getMessage()}";
            }
        }
    }

    private function importEntityUiInfo(string $entityId, object $row): void
    {
        // field => [default (plain string), localized (PHP-serialized)]
        $map = [
            'display_name'    => [$row->displayname ?? null,  $row->ldisplayname ?? null],
            'org_name'        => [$row->name ?? null,         $row->lname ?? null],
            'org_display_name'=> [$row->name ?? null,         $row->lname ?? null],
            'org_url'         => [$row->url ?? null,          $row->lurl ?? null],
            'description'     => [$row->description ?? null,  null],
            'information_url' => [$row->helpdeskurl ?? null,  $row->lhelpdeskurl ?? null],
            'privacy_url'     => [$row->privacyurl ?? null,   $row->lprivacyurl ?? null],
        ];

        foreach ($map as $field => [$default, $localizedRaw]) {
            $entries = [];

            if ($localizedRaw) {
                $localized = $this->phpUnserialize($localizedRaw);
                if (is_array($localized)) {
                    foreach ($localized as $lang => $value) {
                        if ($value !== '' && $value !== null) {
                            $entries[(string) $lang] = (string) $value;
                        }
                    }
                }
            }

            if ($default !== null && $default !== '' && !isset($entries['en'])) {
                $entries['en'] = (string) $default;
            }

            foreach ($entries as $lang => $value) {
                $existing = DB::table('entity_ui_info')
                    ->where('entity_id', $entityId)
                    ->where('field', $field)
                    ->where('lang', $lang)
                    ->value('id');

                if ($existing) {
                    DB::table('entity_ui_info')
                        ->where('id', $existing)
                        ->update(['value' => $value, 'updated_at' => now()]);
                } else {
                    DB::table('entity_ui_info')->insert([
                        'id'         => Str::uuid()->toString(),
                        'entity_id'  => $entityId,
                        'field'      => $field,
                        'lang'       => $lang,
                        'value'      => $value,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    private function mapEntityStatus(bool $approved, bool $active): string
    {
        if ($approved && $active)   return 'active';
        if (!$approved && $active)  return 'pending';
        if ($approved && !$active)  return 'suspended';
        return 'draft';
    }

    // ── Step 3: Federation memberships ───────────────────────────────────────

    private function importMemberships(): void
    {
        foreach ($this->jagger->table('federation_members')->get() as $row) {
            try {
                $fedId    = $this->fedMap[$row->federation_id]  ?? null;
                $entityId = $this->entityMap[$row->provider_id] ?? null;

                if (!$fedId || !$entityId) {
                    $this->stats['memberships']['skipped']++;
                    continue;
                }

                $exists = DB::table('entity_federation')
                    ->where('federation_id', $fedId)
                    ->where('entity_id', $entityId)
                    ->exists();

                if ($exists) {
                    $this->stats['memberships']['skipped']++;
                    continue;
                }

                $status     = $this->mapMembershipStatus(
                    (int)  $row->joinstate,
                    (bool) $row->isdisabled,
                    (bool) $row->isbanned
                );

                if ($status === 'active' && \App\Models\EntityFederation::hasActiveMembership($entityId, $fedId)) {
                    $this->stats['memberships']['skipped']++;
                    $this->importWarnings[] = "Membership [fed:{$row->federation_id} prov:{$row->provider_id}]: skipped — entity already has an active membership in another federation";
                    continue;
                }

                $approvedAt = $status === 'active' ? now() : null;

                DB::table('entity_federation')->insert([
                    'entity_id'     => $entityId,
                    'federation_id' => $fedId,
                    'status'        => $status,
                    'approved_by'   => null,
                    'approved_at'   => $approvedAt,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                $this->stats['memberships']['created']++;
            } catch (Throwable $e) {
                $this->stats['memberships']['errors']++;
                $this->importErrors[] = "Membership [fed:{$row->federation_id} prov:{$row->provider_id}]: {$e->getMessage()}";
            }
        }
    }

    private function mapMembershipStatus(int $joinstate, bool $disabled, bool $banned): string
    {
        if ($banned)   return 'rejected';
        if ($disabled) return 'suspended';
        // joinstate: 0=default, 1=joined, 2=left, 3=synced
        return $joinstate === 2 ? 'suspended' : 'active';
    }

    // ── Step 4: Certificates ─────────────────────────────────────────────────

    private function importCertificates(): void
    {
        foreach ($this->jagger->table('certificate')->get() as $row) {
            try {
                $entityId = $this->entityMap[$row->provider_id] ?? null;
                if (!$entityId || empty($row->certdata)) {
                    $this->stats['certificates']['skipped']++;
                    continue;
                }

                $parsed = $this->parseCert(trim($row->certdata));
                if (!$parsed) {
                    $this->stats['certificates']['skipped']++;
                    $this->importWarnings[] = "Certificate [provider:{$row->provider_id}]: could not parse PEM data";
                    continue;
                }

                $use = match ($row->certusage) {
                    'encryption' => 'encryption',
                    default      => 'signing',
                };

                DB::table('entity_certificates')->insert([
                    'id'                  => Str::uuid()->toString(),
                    'entity_id'           => $entityId,
                    'use'                 => $use,
                    'pem'                 => $parsed['pem'],
                    'subject'             => $parsed['subject'],
                    'issuer'              => $parsed['issuer'],
                    'serial'              => $parsed['serial'],
                    'not_before'          => $parsed['not_before'],
                    'not_after'           => $parsed['not_after'],
                    'key_bits'            => $parsed['key_bits'],
                    'key_algorithm'       => $parsed['key_algorithm'],
                    'fingerprint'         => $parsed['fingerprint'],
                    'signature_algorithm' => $parsed['signature_algorithm'],
                    'debian_weak'         => 0,
                    'created_at'          => now(),
                    'updated_at'          => now(),
                ]);

                $this->stats['certificates']['created']++;
            } catch (Throwable $e) {
                $this->stats['certificates']['errors']++;
                $this->importErrors[] = "Certificate [provider:{$row->provider_id}]: {$e->getMessage()}";
            }
        }
    }

    private function parseCert(string $certData): ?array
    {
        // Jagger certdata may already be chunked with newlines or include PEM
        // headers — strip everything down to bare base64 before re-wrapping.
        $b64 = preg_replace('/\s+/', '', $certData);
        $b64 = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'], '', $b64);

        $pem = "-----BEGIN CERTIFICATE-----\n"
             . chunk_split($b64, 64, "\n")
             . "-----END CERTIFICATE-----\n";

        $parsed = @openssl_x509_parse($pem);
        if (!$parsed) {
            return null;
        }

        $issuerParts = [];
        foreach ((array) ($parsed['issuer'] ?? []) as $k => $v) {
            $issuerParts[] = $k . '=' . (is_array($v) ? implode(',', $v) : $v);
        }

        $keyType = null;
        if (isset($parsed['key']['type'])) {
            $keyType = match ((int) $parsed['key']['type']) {
                OPENSSL_KEYTYPE_RSA => 'RSA',
                OPENSSL_KEYTYPE_DSA => 'DSA',
                OPENSSL_KEYTYPE_EC  => 'EC',
                default             => 'unknown',
            };
        }

        return [
            'pem'                 => $pem,
            'subject'             => $parsed['name'] ?? '',
            'issuer'              => implode(', ', $issuerParts) ?: '',
            'serial'              => $parsed['serialNumberHex'] ?? '',
            'not_before'          => isset($parsed['validFrom_time_t'])
                                         ? date('Y-m-d H:i:s', $parsed['validFrom_time_t'])
                                         : null,
            'not_after'           => isset($parsed['validTo_time_t'])
                                         ? date('Y-m-d H:i:s', $parsed['validTo_time_t'])
                                         : null,
            'key_bits'            => $parsed['bits'] ?? 0,
            'key_algorithm'       => $keyType ?? 'unknown',
            'fingerprint'         => @openssl_x509_fingerprint($pem, 'sha256') ?: '',
            'signature_algorithm' => $parsed['signatureTypeLN'] ?? 'unknown',
        ];
    }

    // ── Step 5: Contacts ─────────────────────────────────────────────────────

    private function importContacts(): void
    {
        $allowed = ['technical', 'administrative', 'support', 'security', 'billing'];

        foreach ($this->jagger->table('contact')->get() as $row) {
            try {
                $entityId = $this->entityMap[$row->provider_id] ?? null;
                if (!$entityId) {
                    $this->stats['contacts']['skipped']++;
                    continue;
                }

                $type = in_array($row->type, $allowed, true) ? $row->type : 'technical';

                DB::table('entity_contacts')->insert([
                    'id'         => Str::uuid()->toString(),
                    'entity_id'  => $entityId,
                    'type'       => $type,
                    'given_name' => $row->givenname ?? null,
                    'sur_name'   => $row->surname ?? null,
                    'email'      => $row->email ?? '',
                    'phone'      => $row->phone ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->stats['contacts']['created']++;
            } catch (Throwable $e) {
                $this->stats['contacts']['errors']++;
                $this->importErrors[] = "Contact [provider:{$row->provider_id}]: {$e->getMessage()}";
            }
        }
    }

    // ── Step 6: Endpoints ────────────────────────────────────────────────────

    private function importEndpoints(): void
    {
        // Map Jagger service-type strings → our type enum values
        $typeMap = [
            'SingleSignOnService'        => 'sso',
            'SSO'                        => 'sso',
            'AssertionConsumerService'   => 'acs',
            'ACS'                        => 'acs',
            'SingleLogoutService'        => 'slo',
            'IDPSingleLogoutService'     => 'slo',
            'SPSingleLogoutService'      => 'slo',
            'SLO'                        => 'slo',
            'ArtifactResolutionService'  => 'artifact',
            'SPArtifactResolutionService'=> 'artifact',
        ];

        foreach ($this->jagger->table('service_location')->get() as $row) {
            try {
                $entityId = $this->entityMap[$row->provider_id] ?? null;
                if (!$entityId) {
                    $this->stats['endpoints']['skipped']++;
                    continue;
                }

                $type = $typeMap[$row->type] ?? null;
                if (!$type) {
                    $this->stats['endpoints']['skipped']++;
                    continue;
                }

                $location = $row->url ?? $row->location ?? null;
                if (empty($location)) {
                    $this->stats['endpoints']['skipped']++;
                    continue;
                }

                $responseLocation = $row->response_url ?? $row->response_location ?? null;

                DB::table('entity_endpoints')->insert([
                    'id'                => Str::uuid()->toString(),
                    'entity_id'         => $entityId,
                    'type'              => $type,
                    'binding'           => $row->binding_name ?? $row->binding ?? null,
                    'location'          => $location,
                    'response_location' => $responseLocation ?: null,
                    'index'             => isset($row->ordered_no) ? (int) $row->ordered_no : (isset($row->index) ? (int) $row->index : null),
                    'is_default'        => isset($row->is_default) ? (bool) $row->is_default : false,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                $this->stats['endpoints']['created']++;
            } catch (Throwable $e) {
                $this->stats['endpoints']['errors']++;
                $this->importWarnings[] = "Endpoint [provider:{$row->provider_id}]: {$e->getMessage()}";
            }
        }
    }

    // ── Step 7: Attribute definitions ────────────────────────────────────────

    private function importAttributes(bool $skipExisting): void
    {
        foreach ($this->jagger->table('attribute')->get() as $row) {
            try {
                // Match by name first, then OID to avoid duplicates.
                $existing = DB::table('attribute_definitions')
                    ->where('name', $row->name)
                    ->orWhere(function ($q) use ($row) {
                        if ($row->oid) {
                            $q->where('saml2_oid', $row->oid);
                        }
                    })
                    ->first();

                if ($existing) {
                    $this->attrMap[$row->id] = $existing->id;
                    $this->stats['attributes']['skipped']++;
                    continue;
                }

                $id = Str::uuid()->toString();

                DB::table('attribute_definitions')->insert([
                    'id'          => $id,
                    'name'        => $row->name,
                    'full_name'   => $row->fullname,
                    'saml2_oid'   => $row->oid  ?: null,
                    'saml1_urn'   => $row->urn  ?: null,
                    'description' => $row->description ?? null,
                    'is_required' => 0,
                    'is_active'   => (int) (bool) ($row->inmetadata ?? 1),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);

                $this->attrMap[$row->id] = $id;
                $this->stats['attributes']['created']++;
            } catch (Throwable $e) {
                $this->stats['attributes']['errors']++;
                $this->importErrors[] = "Attribute [{$row->name}]: {$e->getMessage()}";
            }
        }
    }

    // ── Step 7: Attribute requirements ───────────────────────────────────────

    private function importAttributeRequirements(): void
    {
        foreach ($this->jagger->table('attribute_requirement')->get() as $row) {
            try {
                $attrId = $this->attrMap[$row->attribute_id] ?? null;
                if (!$attrId) {
                    $this->stats['attr_requirements']['skipped']++;
                    continue;
                }

                $isRequired = ($row->status === 'required');

                if ($row->type === 'SP' && $row->sp_id) {
                    $entityId = $this->entityMap[$row->sp_id] ?? null;
                    if (!$entityId) {
                        $this->stats['attr_requirements']['skipped']++;
                        continue;
                    }

                    $exists = DB::table('entity_requested_attributes')
                        ->where('entity_id', $entityId)
                        ->where('attribute_definition_id', $attrId)
                        ->exists();

                    if ($exists) {
                        $this->stats['attr_requirements']['skipped']++;
                        continue;
                    }

                    DB::table('entity_requested_attributes')->insert([
                        'id'                      => Str::uuid()->toString(),
                        'entity_id'               => $entityId,
                        'attribute_definition_id' => $attrId,
                        'is_required'             => (int) $isRequired,
                        'reason'                  => $row->reason ?? null,
                        'created_at'              => now(),
                        'updated_at'              => now(),
                    ]);

                    $this->stats['attr_requirements']['created']++;
                } elseif ($row->type === 'FED' && $row->fed_id) {
                    $fedId = $this->fedMap[$row->fed_id] ?? null;
                    if (!$fedId) {
                        $this->stats['attr_requirements']['skipped']++;
                        continue;
                    }

                    $exists = DB::table('federation_required_attributes')
                        ->where('federation_id', $fedId)
                        ->where('attribute_definition_id', $attrId)
                        ->exists();

                    if ($exists) {
                        $this->stats['attr_requirements']['skipped']++;
                        continue;
                    }

                    DB::table('federation_required_attributes')->insert([
                        'id'                      => Str::uuid()->toString(),
                        'federation_id'           => $fedId,
                        'attribute_definition_id' => $attrId,
                        'is_required'             => (int) $isRequired,
                        'notes'                   => $row->reason ?? null,
                        'created_at'              => now(),
                        'updated_at'              => now(),
                    ]);

                    $this->stats['attr_requirements']['created']++;
                } else {
                    $this->stats['attr_requirements']['skipped']++;
                }
            } catch (Throwable $e) {
                $this->stats['attr_requirements']['errors']++;
                $this->importErrors[] = "AttrReq [attr:{$row->attribute_id}]: {$e->getMessage()}";
            }
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Generates a unique slug for a federation name, appending -2, -3… on collision. */
    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'federation';
        $slug = $base;
        $i    = 2;
        while (DB::table('federations')->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }

    /** Looks up the federation URI from the Jagger DB to use as registration_authority fallback. */
    private function fedRegistrar(?int $jaggerFedId): ?string
    {
        if (!$jaggerFedId) {
            return null;
        }
        $fed = $this->jagger->table('federation')->where('id', $jaggerFedId)->first();
        return $fed?->urn ?? null;
    }

    /** Safely unserializes a PHP-serialized string; returns original on failure. */
    private function phpUnserialize(string $data): mixed
    {
        if (!preg_match('/^[abisd]:/', $data)) {
            return $data;
        }
        $result = @unserialize($data);
        return ($result === false) ? $data : $result;
    }
}
