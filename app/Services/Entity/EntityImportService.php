<?php

declare(strict_types=1);

namespace App\Services\Entity;

use App\Models\AuditLog;
use App\Models\AttributeDefinition;
use App\Models\Entity;
use App\Models\EntityAttribute;
use App\Models\EntityManager;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EntityImportService
{
    // ── SAML namespace map ─────────────────────────────────────────────────────
    private const NAMESPACES = [
        'md'     => 'urn:oasis:names:tc:SAML:2.0:metadata',
        'mdui'   => 'urn:oasis:names:tc:SAML:metadata:ui',
        'mdrpi'  => 'urn:oasis:names:tc:SAML:metadata:rpi',
        'mdattr' => 'urn:oasis:names:tc:SAML:metadata:attribute',
        'saml'   => 'urn:oasis:names:tc:SAML:2.0:assertion',
        'shibmd' => 'urn:mace:shibboleth:metadata:1.0',
        'ds'     => 'http://www.w3.org/2000/09/xmldsig#',
        'remd'   => 'http://refeds.org/metadata',
    ];

    private const XML_LANG_NS = 'http://www.w3.org/XML/1998/namespace';

    // Attribute Name values used in mdattr:EntityAttributes
    private const ATTR_NAME_ENTITY_CATEGORY         = 'http://macedir.org/entity-category';
    private const ATTR_NAME_ENTITY_CATEGORY_SUPPORT = 'http://macedir.org/entity-category-support';
    private const ATTR_NAME_ASSURANCE               = 'urn:oasis:names:tc:SAML:attribute:assurance-certification';

    private const REFEDS_CONTACT_TYPE_SECURITY = 'http://refeds.org/metadata/contactType/security';

    public function __construct(
        private readonly CertificateService $certService,
    ) {}

    // ══════════════════════════════════════════════════════════════════════════
    // fromXml
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Parse a raw SAML2 EntityDescriptor XML string into a structured array.
     *
     * @throws InvalidArgumentException if XML is malformed, root element is missing,
     *                                  or entityID is absent.
     */
    public function fromXml(string $xml): array
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;

        if (!@$dom->loadXML($xml)) {
            throw new InvalidArgumentException('XML is not well-formed.');
        }

        $xpath = new DOMXPath($dom);
        foreach (self::NAMESPACES as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        $root = $xpath->query('/md:EntityDescriptor')->item(0);
        if (!$root) {
            throw new InvalidArgumentException('No EntityDescriptor root element found.');
        }

        $entityId = $root->getAttribute('entityID');
        if (empty($entityId)) {
            throw new InvalidArgumentException('EntityDescriptor is missing entityID attribute.');
        }

        // ── Determine type ─────────────────────────────────────────────────
        $idpDescriptor = $xpath->query('md:IDPSSODescriptor', $root)->item(0);
        $spDescriptor  = $xpath->query('md:SPSSODescriptor',  $root)->item(0);
        $type          = $idpDescriptor !== null ? 'idp' : 'sp';
        $descriptor    = $idpDescriptor ?? $spDescriptor;

        // ── RegistrationInfo ───────────────────────────────────────────────
        $regInfo = $xpath->query('md:Extensions/mdrpi:RegistrationInfo', $root)->item(0);
        $registrationAuthority = $regInfo
            ? $regInfo->getAttribute('registrationAuthority')
            : '';

        $registrationPolicies = [];
        if ($regInfo) {
            foreach ($xpath->query('mdrpi:RegistrationPolicy', $regInfo) as $rp) {
                $lang = $rp->getAttributeNS(self::XML_LANG_NS, 'lang') ?: 'en';
                $url  = trim($rp->textContent);
                if ($url !== '') {
                    $registrationPolicies[] = ['lang' => $lang, 'url' => $url];
                }
            }
        }

        // ── Scope (IdP only) ───────────────────────────────────────────────
        $scopeNode = $descriptor
            ? $xpath->query('md:Extensions/shibmd:Scope', $descriptor)->item(0)
            : null;
        $scope = $scopeNode ? trim($scopeNode->textContent) : null;

        // ── SP boolean attributes ──────────────────────────────────────────
        $wantAssertionsSigned     = true;
        $wantAuthnRequestsSigned  = true;
        if ($spDescriptor) {
            $wantAssertionsSigned    = $this->parseBoolAttr($spDescriptor->getAttribute('WantAssertionsSigned'), true);
            $wantAuthnRequestsSigned = $this->parseBoolAttr($spDescriptor->getAttribute('AuthnRequestsSigned'), true);
        }

        // ── UI Info ────────────────────────────────────────────────────────
        $uiInfo = $this->extractUiInfo($xpath, $descriptor, $root);

        // ── Contacts ───────────────────────────────────────────────────────
        $contacts = $this->extractContacts($xpath, $root);

        // ── Endpoints ─────────────────────────────────────────────────────
        $endpoints = $this->extractEndpoints($xpath, $descriptor, $type);

        // ── Certificates ──────────────────────────────────────────────────
        $certificates = $this->extractCertificates($xpath, $descriptor);

        // ── Entity attributes (categories + assurance) ─────────────────────
        [$entityCategories, $entityCategorySupports, $assuranceProfiles] = $this->extractEntityAttributes($xpath, $root);

        // ── NameID formats ─────────────────────────────────────────────────
        $nameidFormats = [];
        if ($descriptor) {
            foreach ($xpath->query('md:NameIDFormat', $descriptor) as $node) {
                $v = trim($node->textContent);
                if ($v !== '') {
                    $nameidFormats[] = $v;
                }
            }
        }

        // ── Requested attributes (SP only) ────────────────────────────────
        $requestedAttributes = [];
        if ($spDescriptor) {
            foreach ($xpath->query('.//md:RequestedAttribute', $spDescriptor) as $node) {
                $requestedAttributes[] = [
                    'name'        => $node->getAttribute('Name'),
                    'is_required' => $this->parseBoolAttr($node->getAttribute('isRequired'), false),
                ];
            }
        }

        return [
            'entity_id'                      => $entityId,
            'type'                           => $type,
            'registration_authority'         => $registrationAuthority,
            'registration_policies'          => $registrationPolicies,
            'scope'                          => $scope,
            'sp_want_assertions_signed'      => $wantAssertionsSigned,
            'sp_want_authn_requests_signed'  => $wantAuthnRequestsSigned,
            'ui_info'                        => $uiInfo,
            'contacts'                       => $contacts,
            'endpoints'                      => $endpoints,
            'certificates'                   => $certificates,
            'entity_categories'              => $entityCategories,
            'entity_category_supports'       => $entityCategorySupports,
            'assurance_profiles'             => $assuranceProfiles,
            'nameid_formats'                 => $nameidFormats,
            'requested_attributes'           => $requestedAttributes,
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // fromArray
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Validate and normalise a structured (or flat) array into the same
     * format returned by fromXml().
     *
     * Required keys: entity_id, type
     * All other keys default to null / empty array.
     *
     * @throws InvalidArgumentException on missing required fields or invalid type.
     */
    public function fromArray(array $data): array
    {
        if (empty($data['entity_id'])) {
            throw new InvalidArgumentException('entity_id is required.');
        }

        if (empty($data['type']) || !in_array($data['type'], ['idp', 'sp'], true)) {
            throw new InvalidArgumentException('type is required and must be "idp" or "sp".');
        }

        return [
            'entity_id'                      => (string) $data['entity_id'],
            'type'                           => $data['type'],
            'registration_authority'         => $data['registration_authority'] ?? '',
            'registration_policies'          => $data['registration_policies'] ?? [],
            'scope'                          => $data['scope'] ?? null,
            'sp_want_assertions_signed'      => (bool) ($data['sp_want_assertions_signed'] ?? true),
            'sp_want_authn_requests_signed'  => (bool) ($data['sp_want_authn_requests_signed'] ?? true),
            'ui_info'                        => $data['ui_info'] ?? [],
            'contacts'                       => $data['contacts'] ?? [],
            'endpoints'                      => $data['endpoints'] ?? [],
            'certificates'                   => $data['certificates'] ?? [],
            'entity_categories'              => $data['entity_categories'] ?? [],
            'entity_category_supports'       => $data['entity_category_supports'] ?? [],
            'assurance_profiles'             => $data['assurance_profiles'] ?? [],
            'nameid_formats'                 => $data['nameid_formats'] ?? [],
            'requested_attributes'           => $data['requested_attributes'] ?? [],
        ];
    }

    // ══════════════════════════════════════════════════════════════════════════
    // import
    // ══════════════════════════════════════════════════════════════════════════

    /**
     * Create an Entity and all child records from a parsed import array.
     * Data is normalised via fromArray() before use.
     * All writes occur inside a single DB transaction.
     *
     * @throws InvalidArgumentException if required fields are missing.
     * @throws \Throwable on DB error.
     */
    /**
     * Replace an existing entity with fresh data: delete then import.
     * Federation memberships and entity status are preserved across the replace.
     *
     * @throws InvalidArgumentException  if entity_id is missing
     */
    public function replace(array $data, ?User $importedBy = null): Entity
    {
        $normalized = $this->fromArray($data);

        return DB::transaction(function () use ($normalized, $importedBy) {
            $existing = Entity::where('entity_id', $normalized['entity_id'])->first();

            $preservedStatus      = null;
            $preservedFederations = collect();

            if ($existing) {
                $preservedStatus = $existing->status;
                $preservedFederations = $existing->federations()
                    ->withPivot(['status', 'approved_by', 'approved_at'])
                    ->get()
                    ->map(fn ($f) => [
                        'federation_id' => $f->id,
                        'status'        => $f->pivot->status,
                        'approved_by'   => $f->pivot->approved_by,
                        'approved_at'   => $f->pivot->approved_at,
                    ]);

                $existing->forceDelete();
            }

            $entity = $this->import($normalized, $importedBy);

            if ($preservedStatus !== null) {
                $entity->update(['status' => $preservedStatus]);
            }

            foreach ($preservedFederations as $pivot) {
                $entity->federations()->attach($pivot['federation_id'], [
                    'status'      => $pivot['status'],
                    'approved_by' => $pivot['approved_by'],
                    'approved_at' => $pivot['approved_at'],
                ]);
            }

            return $entity->refresh();
        });
    }

    public function import(array $data, ?User $importedBy = null): Entity
    {
        $normalized = $this->fromArray($data);

        return DB::transaction(function () use ($normalized, $importedBy) {
            $entity = Entity::create([
                'entity_id'                      => $normalized['entity_id'],
                'type'                           => $normalized['type'],
                'status'                         => 'draft',
                'registration_authority'         => $normalized['registration_authority'],
                'registration_policies'          => $normalized['registration_policies'] ?: null,
                'created_by'                     => $importedBy?->id,
                'scope'                          => $normalized['scope'],
                'sp_want_assertions_signed'      => $normalized['sp_want_assertions_signed'],
                'sp_want_authn_requests_signed'  => $normalized['sp_want_authn_requests_signed'],
                'nameid_formats'                 => $normalized['nameid_formats'],
                'requested_attributes'           => $normalized['requested_attributes'],
            ]);

            if ($importedBy !== null) {
                EntityManager::create([
                    'entity_id' => $entity->id,
                    'user_id'   => $importedBy->id,
                    'role'      => 'owner',
                    'added_by'  => $importedBy->id,
                    'added_at'  => now(),
                ]);
            }

            foreach ($normalized['ui_info'] as $ui) {
                $entity->uiInfo()->create($ui);
            }

            foreach ($normalized['contacts'] as $contact) {
                $entity->contacts()->create($contact);
            }

            foreach ($normalized['endpoints'] as $endpoint) {
                $entity->endpoints()->create($endpoint);
            }

            foreach ($normalized['entity_categories'] as $uri) {
                $entity->attributes()->create([
                    'attribute_name'  => EntityAttribute::ATTR_ENTITY_CATEGORY,
                    'attribute_value' => $uri,
                ]);
            }

            foreach ($normalized['entity_category_supports'] as $uri) {
                $entity->attributes()->create([
                    'attribute_name'  => EntityAttribute::ATTR_ENTITY_CATEGORY_SUPPORT,
                    'attribute_value' => $uri,
                ]);
            }

            foreach ($normalized['assurance_profiles'] as $uri) {
                $entity->attributes()->create([
                    'attribute_name'  => EntityAttribute::ATTR_ASSURANCE_PROFILE,
                    'attribute_value' => $uri,
                ]);
            }

            // Populate entity_requested_attributes table from imported requested_attributes JSON
            // so the metadata generator can render AttributeConsumingService elements.
            foreach ($normalized['requested_attributes'] as $ra) {
                $name = $ra['name'] ?? '';
                if (empty($name)) {
                    continue;
                }
                $def = AttributeDefinition::where('saml2_oid', $name)
                    ->orWhere('saml1_urn', $name)
                    ->first();
                if ($def) {
                    $entity->entityRequestedAttributes()->firstOrCreate(
                        ['attribute_definition_id' => $def->id],
                        ['is_required' => (bool) ($ra['is_required'] ?? false)],
                    );
                }
            }

            foreach ($normalized['certificates'] as $certData) {
                $parsed = $this->certService->parse($certData['pem']);

                $entity->certificates()->create([
                    'use'                 => $certData['use'],
                    'pem'                 => $certData['pem'],
                    'subject'             => $parsed['subject'],
                    'issuer'              => $parsed['issuer'],
                    'serial'              => $parsed['serial'],
                    'not_before'          => $parsed['not_before'],
                    'not_after'           => $parsed['not_after'],
                    'key_bits'            => $parsed['key_bits'],
                    'key_algorithm'       => $parsed['key_algorithm'],
                    'fingerprint'         => $parsed['fingerprint'],
                    'signature_algorithm' => $parsed['signature_algorithm'],
                    'debian_weak'         => $this->certService->isDebianWeak($parsed['fingerprint']),
                ]);
            }

            AuditLog::create([
                'user_id'    => $importedBy?->id,
                'entity_id'  => $entity->id,
                'action'     => 'entity_imported',
                'old_values' => null,
                'new_values' => [
                    'entity_id' => $entity->entity_id,
                    'type'      => $entity->type,
                ],
                'ip_address' => '127.0.0.1',
                'user_agent' => null,
            ]);

            return $entity;
        });
    }

    public function updateFromData(Entity $entity, array $data, ?User $updatedBy = null): Entity
    {
        $normalized = $this->fromArray($data);

        return DB::transaction(function () use ($entity, $normalized, $updatedBy) {
            $entity->update([
                'type'                           => $normalized['type'],
                'registration_authority'         => $normalized['registration_authority'],
                'registration_policies'          => $normalized['registration_policies'] ?: null,
                'scope'                          => $normalized['scope'],
                'sp_want_assertions_signed'      => $normalized['sp_want_assertions_signed'],
                'sp_want_authn_requests_signed'  => $normalized['sp_want_authn_requests_signed'],
                'nameid_formats'                 => $normalized['nameid_formats'],
                'requested_attributes'           => $normalized['requested_attributes'],
                'last_updated_by'                => $updatedBy?->id,
            ]);

            $entity->uiInfo()->delete();
            $entity->contacts()->delete();
            $entity->endpoints()->delete();
            $entity->certificates()->delete();
            $entity->attributes()->delete();
            $entity->entityRequestedAttributes()->delete();

            foreach ($normalized['ui_info'] as $ui) {
                $entity->uiInfo()->create($ui);
            }

            foreach ($normalized['contacts'] as $contact) {
                $entity->contacts()->create($contact);
            }

            foreach ($normalized['endpoints'] as $endpoint) {
                $entity->endpoints()->create($endpoint);
            }

            foreach ($normalized['entity_categories'] as $uri) {
                $entity->attributes()->create([
                    'attribute_name'  => EntityAttribute::ATTR_ENTITY_CATEGORY,
                    'attribute_value' => $uri,
                ]);
            }

            foreach ($normalized['entity_category_supports'] as $uri) {
                $entity->attributes()->create([
                    'attribute_name'  => EntityAttribute::ATTR_ENTITY_CATEGORY_SUPPORT,
                    'attribute_value' => $uri,
                ]);
            }

            foreach ($normalized['assurance_profiles'] as $uri) {
                $entity->attributes()->create([
                    'attribute_name'  => EntityAttribute::ATTR_ASSURANCE_PROFILE,
                    'attribute_value' => $uri,
                ]);
            }

            foreach ($normalized['requested_attributes'] as $ra) {
                $name = $ra['name'] ?? '';
                if (empty($name)) {
                    continue;
                }
                $def = AttributeDefinition::where('saml2_oid', $name)
                    ->orWhere('saml1_urn', $name)
                    ->first();
                if ($def) {
                    $entity->entityRequestedAttributes()->create([
                        'attribute_definition_id' => $def->id,
                        'is_required'             => (bool) ($ra['is_required'] ?? false),
                    ]);
                }
            }

            foreach ($normalized['certificates'] as $certData) {
                $parsed = $this->certService->parse($certData['pem']);
                $entity->certificates()->create([
                    'use'                 => $certData['use'],
                    'pem'                 => $certData['pem'],
                    'subject'             => $parsed['subject'],
                    'issuer'              => $parsed['issuer'],
                    'serial'              => $parsed['serial'],
                    'not_before'          => $parsed['not_before'],
                    'not_after'           => $parsed['not_after'],
                    'key_bits'            => $parsed['key_bits'],
                    'key_algorithm'       => $parsed['key_algorithm'],
                    'fingerprint'         => $parsed['fingerprint'],
                    'signature_algorithm' => $parsed['signature_algorithm'],
                    'debian_weak'         => $this->certService->isDebianWeak($parsed['fingerprint']),
                ]);
            }

            AuditLog::create([
                'user_id'    => $updatedBy?->id,
                'entity_id'  => $entity->id,
                'action'     => 'entity_updated_from_metadata',
                'old_values' => null,
                'new_values' => ['entity_id' => $entity->entity_id, 'type' => $entity->type],
                'ip_address' => '127.0.0.1',
                'user_agent' => null,
            ]);

            return $entity->refresh();
        });
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Private XML extraction helpers
    // ══════════════════════════════════════════════════════════════════════════

    private function extractUiInfo(DOMXPath $xpath, ?\DOMNode $descriptor, \DOMNode $root): array
    {
        $uiInfo = [];

        if ($descriptor) {
            $uiInfoNode = $xpath->query('md:Extensions/mdui:UIInfo', $descriptor)->item(0);

            if ($uiInfoNode) {
                $this->extractLangNodes($xpath, $uiInfoNode, 'mdui:DisplayName',         'display_name',    $uiInfo);
                $this->extractLangNodes($xpath, $uiInfoNode, 'mdui:Description',         'description',     $uiInfo);
                $this->extractLangNodes($xpath, $uiInfoNode, 'mdui:InformationURL',      'information_url', $uiInfo);
                $this->extractLangNodes($xpath, $uiInfoNode, 'mdui:PrivacyStatementURL', 'privacy_url',     $uiInfo);

                foreach ($xpath->query('mdui:Logo', $uiInfoNode) as $logo) {
                    $lang = $logo->getAttributeNS(self::XML_LANG_NS, 'lang') ?: 'en';
                    $val  = trim($logo->textContent);
                    if ($val !== '') {
                        $uiInfo[] = [
                            'field'       => 'logo_url',
                            'lang'        => $lang,
                            'value'       => $val,
                            'logo_height' => (int) $logo->getAttribute('height') ?: null,
                            'logo_width'  => (int) $logo->getAttribute('width')  ?: null,
                        ];
                    }
                }
            }
        }

        // Organization — sits directly under EntityDescriptor
        $org = $xpath->query('md:Organization', $root)->item(0);
        if ($org) {
            $this->extractLangNodes($xpath, $org, 'md:OrganizationName',        'org_name',         $uiInfo);
            $this->extractLangNodes($xpath, $org, 'md:OrganizationDisplayName', 'org_display_name', $uiInfo);
            $this->extractLangNodes($xpath, $org, 'md:OrganizationURL',         'org_url',          $uiInfo);
        }

        return $uiInfo;
    }

    private function extractLangNodes(
        DOMXPath $xpath,
        \DOMNode $context,
        string   $query,
        string   $field,
        array   &$uiInfo,
    ): void {
        foreach ($xpath->query($query, $context) as $node) {
            $lang  = $node->getAttributeNS(self::XML_LANG_NS, 'lang') ?: 'en';
            $value = trim($node->textContent);
            if ($value !== '') {
                $uiInfo[] = ['field' => $field, 'lang' => $lang, 'value' => $value];
            }
        }
    }

    private function extractContacts(DOMXPath $xpath, \DOMNode $root): array
    {
        $contacts = [];

        foreach ($xpath->query('md:ContactPerson', $root) as $cp) {
            $type = $cp->getAttribute('contactType');

            // REFEDS security contacts use contactType="other" + remd:contactType extension
            if ($type === 'other') {
                $remdType = $cp->getAttributeNS('http://refeds.org/metadata', 'contactType');
                if ($remdType === self::REFEDS_CONTACT_TYPE_SECURITY) {
                    $type = 'security';
                }
            }

            $email = '';

            $emailNode = $xpath->query('md:EmailAddress', $cp)->item(0);
            if ($emailNode) {
                $raw   = trim($emailNode->textContent);
                $email = str_starts_with($raw, 'mailto:') ? substr($raw, 7) : $raw;
            }

            if (empty($type) || empty($email)) {
                continue;
            }

            $contact = ['type' => $type, 'email' => $email];

            $givenNode = $xpath->query('md:GivenName', $cp)->item(0);
            $surNode   = $xpath->query('md:SurName',   $cp)->item(0);

            $contact['given_name'] = $givenNode ? trim($givenNode->textContent) : null;
            $contact['sur_name']   = $surNode   ? trim($surNode->textContent)   : null;

            $contacts[] = $contact;
        }

        return $contacts;
    }

    private function extractEndpoints(DOMXPath $xpath, ?\DOMNode $descriptor, string $type): array
    {
        $endpoints = [];

        if (!$descriptor) {
            return $endpoints;
        }

        if ($type === 'idp') {
            foreach ($xpath->query('md:SingleSignOnService', $descriptor) as $node) {
                $endpoints[] = [
                    'type'     => 'sso',
                    'binding'  => $node->getAttribute('Binding'),
                    'location' => $node->getAttribute('Location'),
                ];
            }
        } else {
            $acsIndex = 1;
            foreach ($xpath->query('md:AssertionConsumerService', $descriptor) as $node) {
                $idxAttr = $node->getAttribute('index');
                $idx     = $idxAttr !== '' ? (int) $idxAttr : $acsIndex;
                $def     = $this->parseBoolAttr($node->getAttribute('isDefault'), $acsIndex === 1);

                $endpoints[] = [
                    'type'       => 'acs',
                    'binding'    => $node->getAttribute('Binding'),
                    'location'   => $node->getAttribute('Location'),
                    'index'      => $idx,
                    'is_default' => $def,
                ];
                $acsIndex++;
            }
        }

        // SLO for both types
        foreach ($xpath->query('md:SingleLogoutService', $descriptor) as $node) {
            $endpoints[] = [
                'type'     => 'slo',
                'binding'  => $node->getAttribute('Binding'),
                'location' => $node->getAttribute('Location'),
            ];
        }

        return $endpoints;
    }

    private function extractCertificates(DOMXPath $xpath, ?\DOMNode $descriptor): array
    {
        $certs = [];

        if (!$descriptor) {
            return $certs;
        }

        foreach ($xpath->query('md:KeyDescriptor', $descriptor) as $kd) {
            $use     = $kd->getAttribute('use') ?: 'signing';
            $x509    = $xpath->query('.//ds:X509Certificate', $kd)->item(0);
            if (!$x509) {
                continue;
            }

            $base64 = preg_replace('/\s+/', '', $x509->textContent);
            if (empty($base64)) {
                continue;
            }

            $pem = "-----BEGIN CERTIFICATE-----\n"
                 . chunk_split($base64, 64, "\n")
                 . "-----END CERTIFICATE-----\n";

            $certs[] = ['use' => $use, 'pem' => $pem];
        }

        return $certs;
    }

    private function extractEntityAttributes(DOMXPath $xpath, \DOMNode $root): array
    {
        $entityCategories        = [];
        $entityCategorySupports  = [];
        $assuranceProfiles       = [];

        foreach ($xpath->query('md:Extensions/mdattr:EntityAttributes/saml:Attribute', $root) as $attr) {
            $name = $attr->getAttribute('Name');

            foreach ($xpath->query('saml:AttributeValue', $attr) as $av) {
                $value = trim($av->textContent);
                if ($value === '') {
                    continue;
                }

                if ($name === self::ATTR_NAME_ENTITY_CATEGORY) {
                    $entityCategories[] = $value;
                } elseif ($name === self::ATTR_NAME_ENTITY_CATEGORY_SUPPORT) {
                    $entityCategorySupports[] = $value;
                } elseif ($name === self::ATTR_NAME_ASSURANCE) {
                    $assuranceProfiles[] = $value;
                }
            }
        }

        return [$entityCategories, $entityCategorySupports, $assuranceProfiles];
    }

    private function parseBoolAttr(string $value, bool $default): bool
    {
        if ($value === '') {
            return $default;
        }
        return in_array(strtolower($value), ['true', '1', 'yes'], true);
    }
}
