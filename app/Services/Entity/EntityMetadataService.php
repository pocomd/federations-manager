<?php

declare(strict_types=1);

namespace App\Services\Entity;

use App\Exceptions\XmlSigningException;
use App\Models\Entity;
use App\Models\EntityAttribute;
use App\Models\EntityValidationResult;
use App\Services\Metadata\RuleEngine;
use App\Services\Signing\SigningDriverFactory;
use DOMDocument;
use DOMElement;

class EntityMetadataService
{
    public const XML_CACHE_TTL = 3600;

    public static function xmlCacheKey(Entity $entity): string
    {
        return 'entity_xml:' . $entity->id;
    }

    // ── SAML namespace constants ───────────────────────────────────────────
    private const NS_MD     = 'urn:oasis:names:tc:SAML:2.0:metadata';
    private const NS_MDUI   = 'urn:oasis:names:tc:SAML:metadata:ui';
    private const NS_MDRPI  = 'urn:oasis:names:tc:SAML:metadata:rpi';
    private const NS_MDATTR = 'urn:oasis:names:tc:SAML:metadata:attribute';
    private const NS_SAML   = 'urn:oasis:names:tc:SAML:2.0:assertion';
    private const NS_SHIBMD = 'urn:mace:shibboleth:metadata:1.0';
    private const NS_DS     = 'http://www.w3.org/2000/09/xmldsig#';

    // ── REFEDS / RFC 8409 canonical URIs ──────────────────────────────────
    private const URI_ENTITY_CAT         = 'http://macedir.org/entity-category';
    private const URI_ENTITY_CAT_SUPPORT = 'http://macedir.org/entity-category-support';
    private const NS_REMD                = 'http://refeds.org/metadata';

    // ── SSO/ACS binding URNs ───────────────────────────────────────────────
    private const BINDING_POST     = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST';
    private const BINDING_REDIRECT = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect';

    public function __construct(
        private readonly CertificateService   $certService,
        private readonly SigningDriverFactory  $signingFactory,
        private readonly RuleEngine            $ruleEngine,
    ) {}

    // ══════════════════════════════════════════════════════════════════════
    // Validation
    // ══════════════════════════════════════════════════════════════════════

    public function validate(Entity $entity): ValidationResult
    {
        $entity->loadMissing(['certificates', 'uiInfo', 'contacts', 'endpoints', 'attributes']);

        $checks = array_map(
            fn($r) => $r->toArray(),
            $this->ruleEngine->evaluate($entity),
        );

        $result = ValidationResult::fromChecks($checks);

        // Persist to audit log — skipped for in-memory entities (unit tests)
        if ($entity->exists) {
            EntityValidationResult::create([
                'entity_id'    => $entity->id,
                'passed'       => $result->passed(),
                'errors'       => $result->errors(),
                'warnings'     => $result->warnings(),
                'checks'       => $result->checks(),
                'triggered_by' => 'system',
            ]);
        }

        return $result;
    }

    // ══════════════════════════════════════════════════════════════════════
    // XML Generation
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Render a valid SAML2 EntityDescriptor XML string.
     * All namespace prefixes from CONTEXT.md are declared on the root element.
     */
    public function renderXml(Entity $entity): string
    {
        $entity->loadMissing(['certificates', 'uiInfo', 'contacts', 'endpoints', 'attributes', 'entityRequestedAttributes.attributeDefinition']);

        $federation = $entity->federations()
            ->wherePivot('status', 'active')
            ->with('enabledPolicies')
            ->first();

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        // ── Root: md:EntityDescriptor ──────────────────────────────────────
        $root = $dom->createElementNS(self::NS_MD, 'md:EntityDescriptor');
        $dom->appendChild($root);

        $root->setAttribute('xmlns:md',     self::NS_MD);
        $root->setAttribute('xmlns:mdui',   self::NS_MDUI);
        $root->setAttribute('xmlns:mdrpi',  self::NS_MDRPI);
        $root->setAttribute('xmlns:mdattr', self::NS_MDATTR);
        $root->setAttribute('xmlns:saml',   self::NS_SAML);
        $root->setAttribute('xmlns:shibmd', self::NS_SHIBMD);
        $root->setAttribute('xmlns:ds',     self::NS_DS);
        $root->setAttribute('entityID',     $entity->entity_id);

        if (! empty($entity->sha1_entity_id)) {
            $root->setAttribute('ID', '_' . $entity->sha1_entity_id);
        }

        // ── md:Extensions (top-level) — only emitted when non-empty ───────
        $extensions = $dom->createElementNS(self::NS_MD, 'md:Extensions');
        $this->appendRegistrationInfo($dom, $extensions, $entity, $federation);
        $this->appendEntityAttributes($dom, $extensions, $entity);
        if ($extensions->hasChildNodes()) {
            $root->appendChild($extensions);
        }

        // ── Role descriptor (IdP or SP) ────────────────────────────────────
        if ($entity->type === 'idp') {
            $this->appendIdpSsoDescriptor($dom, $root, $entity);
        } else {
            $this->appendSpSsoDescriptor($dom, $root, $entity);
        }

        // ── md:Organization ────────────────────────────────────────────────
        $this->appendOrganization($dom, $root, $entity);

        // ── md:ContactPerson ───────────────────────────────────────────────
        $this->appendContactPersons($dom, $root, $entity);

        return $dom->saveXML();
    }

    /**
     * Render XML then sign it with xmlsectool.
     *
     * @throws XmlSigningException if the entity has no active federation or signing is not configured.
     */
    public function renderSignedXml(Entity $entity): string
    {
        $federation = $entity->federations()->wherePivot('status', 'active')->first();

        if (! $federation) {
            throw new XmlSigningException('Entity has no active federation — cannot determine signing keys.');
        }

        return $this->signingFactory->make($federation)->sign($this->renderXml($entity), $federation);
    }

    // ── XML builder helpers ────────────────────────────────────────────────

    private function appendRegistrationInfo(
        DOMDocument $dom,
        DOMElement $parent,
        Entity $entity,
        ?\App\Models\Federation $federation = null
    ): void {
        if (empty($entity->registration_authority)) {
            return;
        }

        $ri = $dom->createElementNS(self::NS_MDRPI, 'mdrpi:RegistrationInfo');
        $ri->setAttribute('registrationAuthority', $entity->registration_authority);

        $instant = $federation?->pivot?->approved_at
            ?? $federation?->pivot?->created_at
            ?? $entity->created_at;
        $ri->setAttribute(
            'registrationInstant',
            \Carbon\Carbon::parse($instant)->utc()->format('Y-m-d\TH:i:s\Z')
        );

        $federationPolicies = $federation?->enabledPolicies ?? collect();

        if ($federationPolicies->isNotEmpty()) {
            foreach ($federationPolicies as $policy) {
                $rp = $dom->createElementNS(self::NS_MDRPI, 'mdrpi:RegistrationPolicy');
                $rp->setAttribute('xml:lang', $policy->lang);
                $rp->appendChild($dom->createTextNode($policy->url));
                $ri->appendChild($rp);
            }
        } elseif (! empty($entity->registration_policies)) {
            foreach ($entity->registration_policies as $policy) {
                $rp = $dom->createElementNS(self::NS_MDRPI, 'mdrpi:RegistrationPolicy');
                $rp->setAttribute('xml:lang', $policy['lang']);
                $rp->appendChild($dom->createTextNode($policy['url']));
                $ri->appendChild($rp);
            }
        }

        $parent->appendChild($ri);
    }

    private function appendEntityAttributes(DOMDocument $dom, DOMElement $parent, Entity $entity): void
    {
        $entityCategories = $entity->attributes
            ->where('attribute_name', EntityAttribute::ATTR_ENTITY_CATEGORY)
            ->pluck('attribute_value');

        // RFC 8409 §4: entity-category-support (IdPs only) — emitted only for eduGAIN entities
        $entityCategorySupport = $entity->edugain
            ? $entity->attributes
                ->where('attribute_name', EntityAttribute::ATTR_ENTITY_CATEGORY_SUPPORT)
                ->pluck('attribute_value')
            : collect();

        $assuranceProfiles = $entity->attributes
            ->where('attribute_name', EntityAttribute::ATTR_ASSURANCE_PROFILE)
            ->pluck('attribute_value');

        if ($entityCategories->isEmpty() && $entityCategorySupport->isEmpty() && $assuranceProfiles->isEmpty()) {
            return;
        }

        // Use createElement (not createElementNS) for child elements — their namespaces
        // are already declared on the root EntityDescriptor, so createElementNS would
        // emit redundant xmlns declarations on every child element.
        $entityAttrs = $dom->createElement('mdattr:EntityAttributes');
        $parent->appendChild($entityAttrs);

        if ($entityCategories->isNotEmpty()) {
            $attr = $dom->createElement('saml:Attribute');
            $attr->setAttribute('Name', self::URI_ENTITY_CAT);
            $attr->setAttribute('NameFormat', 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri');
            $entityAttrs->appendChild($attr);

            foreach ($entityCategories as $uri) {
                $val = $dom->createElement('saml:AttributeValue', htmlspecialchars($uri));
                $attr->appendChild($val);
            }
        }

        if ($entityCategorySupport->isNotEmpty()) {
            $attr = $dom->createElement('saml:Attribute');
            $attr->setAttribute('Name', self::URI_ENTITY_CAT_SUPPORT);
            $attr->setAttribute('NameFormat', 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri');
            $entityAttrs->appendChild($attr);

            foreach ($entityCategorySupport as $uri) {
                $val = $dom->createElement('saml:AttributeValue', htmlspecialchars($uri));
                $attr->appendChild($val);
            }
        }

        if ($assuranceProfiles->isNotEmpty()) {
            $attr = $dom->createElement('saml:Attribute');
            $attr->setAttribute('Name', 'urn:oasis:names:tc:SAML:attribute:assurance-certification');
            $attr->setAttribute('NameFormat', 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri');
            $entityAttrs->appendChild($attr);

            foreach ($assuranceProfiles as $uri) {
                $val = $dom->createElement('saml:AttributeValue', htmlspecialchars($uri));
                $attr->appendChild($val);
            }
        }
    }

    private function appendIdpSsoDescriptor(DOMDocument $dom, DOMElement $root, Entity $entity): void
    {
        $idp = $dom->createElementNS(self::NS_MD, 'md:IDPSSODescriptor');
        $idp->setAttribute('protocolSupportEnumeration', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $root->appendChild($idp);

        // Extensions: Scope then UIInfo — only emitted when non-empty
        $ext = $dom->createElementNS(self::NS_MD, 'md:Extensions');
        if (! empty($entity->scope)) {
            $scope = $dom->createElementNS(self::NS_SHIBMD, 'shibmd:Scope', htmlspecialchars($entity->scope));
            $scope->setAttribute('regexp', 'false');
            $ext->appendChild($scope);
        }
        $this->appendUiInfo($dom, $ext, $entity);
        if ($ext->hasChildNodes()) {
            $idp->appendChild($ext);
        }

        // SAML spec §2.4.3 ordering: KeyDescriptor → ArtifactResolutionService → SLO → NameIDFormat → SSO
        $this->appendKeyDescriptors($dom, $idp, $entity);
        $this->appendArtifactResolutionServices($dom, $idp, $entity);
        $this->appendSloServices($dom, $idp, $entity);

        foreach ($entity->nameid_formats ?? [] as $format) {
            $nid = $dom->createElementNS(self::NS_MD, 'md:NameIDFormat', htmlspecialchars($format));
            $idp->appendChild($nid);
        }

        foreach ($entity->getEndpoints('sso') as $ep) {
            $sso = $dom->createElementNS(self::NS_MD, 'md:SingleSignOnService');
            $sso->setAttribute('Binding', $ep->binding);
            $sso->setAttribute('Location', $ep->location);
            $idp->appendChild($sso);
        }
    }

    private function appendSpSsoDescriptor(DOMDocument $dom, DOMElement $root, Entity $entity): void
    {
        $sp = $dom->createElementNS(self::NS_MD, 'md:SPSSODescriptor');
        $sp->setAttribute('protocolSupportEnumeration', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $sp->setAttribute('AuthnRequestsSigned',  $entity->sp_want_authn_requests_signed ? 'true' : 'false');
        $sp->setAttribute('WantAssertionsSigned', $entity->sp_want_assertions_signed     ? 'true' : 'false');
        $root->appendChild($sp);

        // Extensions: UIInfo — only emitted when non-empty
        $ext = $dom->createElementNS(self::NS_MD, 'md:Extensions');
        $this->appendUiInfo($dom, $ext, $entity);
        if ($ext->hasChildNodes()) {
            $sp->appendChild($ext);
        }

        // SAML spec §2.4.4 ordering: KeyDescriptor → ArtifactResolutionService → SLO → ACS → ACS
        $this->appendKeyDescriptors($dom, $sp, $entity);
        $this->appendArtifactResolutionServices($dom, $sp, $entity);
        $this->appendSloServices($dom, $sp, $entity);

        foreach ($entity->getEndpoints('acs') as $ep) {
            $acs = $dom->createElementNS(self::NS_MD, 'md:AssertionConsumerService');
            $acs->setAttribute('Binding', $ep->binding);
            $acs->setAttribute('Location', $ep->location);
            if ($ep->index !== null) {
                $acs->setAttribute('index', (string) $ep->index);
            }
            if ($ep->is_default) {
                $acs->setAttribute('isDefault', 'true');
            }
            if (! empty($ep->response_location)) {
                $acs->setAttribute('ResponseLocation', $ep->response_location);
            }
            $sp->appendChild($acs);
        }

        // AttributeConsumingService — SAML Metadata §2.4.4: RequestedAttribute must be inside ACS
        $requestedAttrs = $entity->entityRequestedAttributes
            ->filter(fn($ra) => $ra->relationLoaded('attributeDefinition') && $ra->attributeDefinition !== null);

        if ($requestedAttrs->isNotEmpty()) {
            $acs = $dom->createElementNS(self::NS_MD, 'md:AttributeConsumingService');
            $acs->setAttribute('index', '0');
            $acs->setAttribute('isDefault', 'true');
            $sp->appendChild($acs);

            $displayNames = $entity->uiInfo->where('field', 'display_name');
            $displayName  = $displayNames->where('lang', 'en')->first() ?? $displayNames->first();
            if ($displayName) {
                $sn = $dom->createElementNS(self::NS_MD, 'md:ServiceName', htmlspecialchars($displayName->value));
                $sn->setAttribute('xml:lang', $displayName->lang);
                $acs->appendChild($sn);
            }

            $descriptions = $entity->uiInfo->where('field', 'description');
            $description  = $descriptions->where('lang', 'en')->first() ?? $descriptions->first();
            if ($description) {
                $sd = $dom->createElementNS(self::NS_MD, 'md:ServiceDescription', htmlspecialchars($description->value));
                $sd->setAttribute('xml:lang', $description->lang);
                $acs->appendChild($sd);
            }

            foreach ($requestedAttrs as $ra) {
                $def  = $ra->attributeDefinition;
                $raEl = $dom->createElementNS(self::NS_MD, 'md:RequestedAttribute');
                $raEl->setAttribute('Name', $def->saml2_oid ?? $def->name);
                $raEl->setAttribute('NameFormat', 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri');
                if (! empty($def->name)) {
                    $raEl->setAttribute('FriendlyName', $def->name);
                }
                $raEl->setAttribute('isRequired', $ra->is_required ? 'true' : 'false');
                $acs->appendChild($raEl);
            }
        }
    }

    private function appendUiInfo(DOMDocument $dom, DOMElement $parent, Entity $entity): void
    {
        $ui = $dom->createElementNS(self::NS_MDUI, 'mdui:UIInfo');

        foreach ($entity->uiInfo->where('field', 'display_name')->sortBy('lang') as $item) {
            $el = $dom->createElementNS(self::NS_MDUI, 'mdui:DisplayName', htmlspecialchars($item->value));
            $el->setAttribute('xml:lang', $item->lang);
            $ui->appendChild($el);
        }

        foreach ($entity->uiInfo->where('field', 'description')->sortBy('lang') as $item) {
            $el = $dom->createElementNS(self::NS_MDUI, 'mdui:Description', htmlspecialchars($item->value));
            $el->setAttribute('xml:lang', $item->lang);
            $ui->appendChild($el);
        }

        foreach ($entity->uiInfo->where('field', 'information_url')->sortBy('lang') as $item) {
            $el = $dom->createElementNS(self::NS_MDUI, 'mdui:InformationURL', htmlspecialchars($item->value));
            $el->setAttribute('xml:lang', $item->lang);
            $ui->appendChild($el);
        }

        foreach ($entity->uiInfo->where('field', 'privacy_url')->sortBy('lang') as $item) {
            $el = $dom->createElementNS(self::NS_MDUI, 'mdui:PrivacyStatementURL', htmlspecialchars($item->value));
            $el->setAttribute('xml:lang', $item->lang);
            $ui->appendChild($el);
        }

        foreach ($entity->uiInfo->where('field', 'logo_url') as $item) {
            $el = $dom->createElementNS(self::NS_MDUI, 'mdui:Logo', htmlspecialchars($item->value));
            $el->setAttribute('height', (string) ($item->logo_height ?? 0));
            $el->setAttribute('width',  (string) ($item->logo_width  ?? 0));
            $ui->appendChild($el);
        }

        if ($ui->hasChildNodes()) {
            $parent->appendChild($ui);
        }
    }

    private function appendKeyDescriptors(DOMDocument $dom, DOMElement $parent, Entity $entity): void
    {
        foreach ($entity->certificates as $cert) {
            $use     = $cert->use === 'both' ? ['signing', 'encryption'] : [$cert->use];
            $pemData = preg_replace('/-----[^-]+-----|\s+/', '', $cert->pem);

            foreach ($use as $useType) {
                $kd     = $dom->createElementNS(self::NS_MD, 'md:KeyDescriptor');
                $kd->setAttribute('use', $useType);
                $parent->appendChild($kd);

                $ki     = $dom->createElementNS(self::NS_DS, 'ds:KeyInfo');
                $x509   = $dom->createElementNS(self::NS_DS, 'ds:X509Data');
                $certEl = $dom->createElementNS(self::NS_DS, 'ds:X509Certificate', $pemData);
                $kd->appendChild($ki);
                $ki->appendChild($x509);
                $x509->appendChild($certEl);
            }
        }
    }

    private function appendSloServices(DOMDocument $dom, DOMElement $parent, Entity $entity): void
    {
        foreach ($entity->getEndpoints('slo') as $ep) {
            $slo = $dom->createElementNS(self::NS_MD, 'md:SingleLogoutService');
            $slo->setAttribute('Binding', $ep->binding);
            $slo->setAttribute('Location', $ep->location);
            if (! empty($ep->response_location)) {
                $slo->setAttribute('ResponseLocation', $ep->response_location);
            }
            $parent->appendChild($slo);
        }
    }

    private function appendArtifactResolutionServices(DOMDocument $dom, DOMElement $parent, Entity $entity): void
    {
        foreach ($entity->getEndpoints('artifact') as $ep) {
            $ars = $dom->createElementNS(self::NS_MD, 'md:ArtifactResolutionService');
            $ars->setAttribute('Binding', $ep->binding);
            $ars->setAttribute('Location', $ep->location);
            if ($ep->index !== null) {
                $ars->setAttribute('index', (string) $ep->index);
            }
            if ($ep->is_default) {
                $ars->setAttribute('isDefault', 'true');
            }
            $parent->appendChild($ars);
        }
    }

    private function appendOrganization(DOMDocument $dom, DOMElement $root, Entity $entity): void
    {
        $org = $dom->createElementNS(self::NS_MD, 'md:Organization');

        foreach ($entity->uiInfo->where('field', 'org_name')->sortBy('lang') as $item) {
            $el = $dom->createElementNS(self::NS_MD, 'md:OrganizationName', htmlspecialchars($item->value));
            $el->setAttribute('xml:lang', $item->lang);
            $org->appendChild($el);
        }

        foreach ($entity->uiInfo->where('field', 'org_display_name')->sortBy('lang') as $item) {
            $el = $dom->createElementNS(self::NS_MD, 'md:OrganizationDisplayName', htmlspecialchars($item->value));
            $el->setAttribute('xml:lang', $item->lang);
            $org->appendChild($el);
        }

        foreach ($entity->uiInfo->where('field', 'org_url')->sortBy('lang') as $item) {
            $el = $dom->createElementNS(self::NS_MD, 'md:OrganizationURL', htmlspecialchars($item->value));
            $el->setAttribute('xml:lang', $item->lang);
            $org->appendChild($el);
        }

        if ($org->hasChildNodes()) {
            $root->appendChild($org);
        }
    }

    // SAML 2.0 schema allows only these contactType values.
    // 'security' (used for SIRTFI) is mapped to 'other' to preserve schema validity.
    private const SAML2_CONTACT_TYPES = ['technical', 'support', 'administrative', 'billing', 'other'];

    private function appendContactPersons(DOMDocument $dom, DOMElement $root, Entity $entity): void
    {
        foreach ($entity->contacts as $contact) {
            $cp = $dom->createElementNS(self::NS_MD, 'md:ContactPerson');
            $contactType = in_array($contact->type, self::SAML2_CONTACT_TYPES, true)
                ? $contact->type
                : 'other';
            $cp->setAttribute('contactType', $contactType);
            $root->appendChild($cp);

            // REFEDS SIRTFI v2.0 §3.2: security contacts must carry remd:contactType extension
            if ($contact->type === 'security') {
                $cp->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:remd', self::NS_REMD);
                $cp->setAttributeNS(self::NS_REMD, 'remd:contactType', self::NS_REMD . '/contactType/security');
            }

            if (! empty($contact->given_name)) {
                $gn = $dom->createElementNS(self::NS_MD, 'md:GivenName', htmlspecialchars($contact->given_name));
                $cp->appendChild($gn);
            }

            if (! empty($contact->sur_name)) {
                $sn = $dom->createElementNS(self::NS_MD, 'md:SurName', htmlspecialchars($contact->sur_name));
                $cp->appendChild($sn);
            }

            $mail = $dom->createElementNS(self::NS_MD, 'md:EmailAddress',
                'mailto:' . htmlspecialchars($contact->email));
            $cp->appendChild($mail);

            if (! empty($contact->phone)) {
                $tel = $dom->createElementNS(self::NS_MD, 'md:TelephoneNumber',
                    htmlspecialchars($contact->phone));
                $cp->appendChild($tel);
            }
        }
    }

}
