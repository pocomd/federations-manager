<?php

declare(strict_types=1);

namespace App\Services\Signing;

use App\Exceptions\XmlSigningException;
use DOMDocument;

class SignedMetadataValidator
{
    private const MD_NS = 'urn:oasis:names:tc:SAML:2.0:metadata';

    private const MAX_VALID_UNTIL_DAYS = 14;

    /**
     * Verify that signed XML output matches structural invariants of the input.
     *
     * Called by both FileSigningDriver and SoftHsmSigningDriver after xmlsectool returns
     * signed XML, before that XML is cached or persisted. Throws XmlSigningException if
     * any invariant fails — caller must discard the output and not serve it.
     *
     * @throws XmlSigningException
     */
    public function validate(string $inputXml, string $signedXml): void
    {
        $input  = $this->parse($inputXml, 'input');
        $signed = $this->parse($signedXml, 'signed output');

        $this->assertEntitiesDescriptorRoot($signed);
        $this->assertEntityCountMatches($input, $signed);
        $this->assertEntityIdsMatch($input, $signed);
        $this->assertValidUntilSane($signed);
    }

    private function parse(string $xml, string $label): DOMDocument
    {
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;

        if (! @$doc->loadXML($xml)) {
            throw new XmlSigningException("SignedMetadataValidator: could not parse {$label} XML.");
        }

        return $doc;
    }

    private function assertEntitiesDescriptorRoot(DOMDocument $doc): void
    {
        $root = $doc->documentElement;

        if (
            $root === null
            || $root->localName !== 'EntitiesDescriptor'
            || $root->namespaceURI !== self::MD_NS
        ) {
            throw new XmlSigningException(
                'SignedMetadataValidator: signed output root is not <md:EntitiesDescriptor> — ' .
                'xmlsectool may have returned an error document.'
            );
        }
    }

    private function assertEntityCountMatches(DOMDocument $input, DOMDocument $signed): void
    {
        $inputCount  = $input->getElementsByTagNameNS(self::MD_NS, 'EntityDescriptor')->length;
        $signedCount = $signed->getElementsByTagNameNS(self::MD_NS, 'EntityDescriptor')->length;

        if ($inputCount !== $signedCount) {
            throw new XmlSigningException(
                "SignedMetadataValidator: entity count mismatch — " .
                "input had {$inputCount}, signed output has {$signedCount}."
            );
        }
    }

    private function assertEntityIdsMatch(DOMDocument $input, DOMDocument $signed): void
    {
        $inputIds  = $this->collectEntityIds($input);
        $signedIds = $this->collectEntityIds($signed);

        $missing = array_diff($inputIds, $signedIds);
        $extra   = array_diff($signedIds, $inputIds);

        if ($missing || $extra) {
            $detail = '';
            if ($missing) {
                $detail .= ' Missing: ' . implode(', ', array_slice($missing, 0, 3));
            }
            if ($extra) {
                $detail .= ' Extra: ' . implode(', ', array_slice($extra, 0, 3));
            }
            throw new XmlSigningException(
                "SignedMetadataValidator: entityID mismatch between input and signed output.{$detail}"
            );
        }
    }

    private function assertValidUntilSane(DOMDocument $signed): void
    {
        $root       = $signed->documentElement;
        $validUntil = $root?->getAttribute('validUntil');

        if (! $validUntil) {
            return;
        }

        try {
            $dt  = new \DateTimeImmutable($validUntil);
            $now = new \DateTimeImmutable();
        } catch (\Throwable) {
            throw new XmlSigningException(
                "SignedMetadataValidator: could not parse validUntil value: \"{$validUntil}\"."
            );
        }

        if ($dt <= $now) {
            throw new XmlSigningException(
                "SignedMetadataValidator: validUntil \"{$validUntil}\" is already in the past."
            );
        }

        $maxFuture = $now->modify('+' . self::MAX_VALID_UNTIL_DAYS . ' days');
        if ($dt > $maxFuture) {
            throw new XmlSigningException(
                "SignedMetadataValidator: validUntil \"{$validUntil}\" is more than " .
                self::MAX_VALID_UNTIL_DAYS . ' days in the future — possible tampering.'
            );
        }
    }

    /** @return string[] */
    private function collectEntityIds(DOMDocument $doc): array
    {
        $ids  = [];
        $nodes = $doc->getElementsByTagNameNS(self::MD_NS, 'EntityDescriptor');

        foreach ($nodes as $node) {
            $ids[] = $node->getAttribute('entityID');
        }

        sort($ids);

        return $ids;
    }
}
