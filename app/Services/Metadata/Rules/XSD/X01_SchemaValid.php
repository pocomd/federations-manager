<?php

declare(strict_types=1);

namespace App\Services\Metadata\Rules\XSD;

use App\Models\Entity;
use App\Services\Metadata\Rules\Contracts\MetadataRule;
use App\Services\Metadata\Rules\Contracts\RuleResult;
use App\Services\Entity\EntityMetadataService;

final class X01_SchemaValid implements MetadataRule
{
    public function __construct(
        private readonly EntityMetadataService $metadataService,
    ) {}

    public function id(): string          { return 'X01'; }
    public function name(): string        { return 'Metadata passes SAML2 schema validation'; }
    public function group(): string       { return 'xsd'; }
    public function appliesTo(): array    { return ['idp', 'sp']; }
    public function defaultSeverity(): string { return 'error'; }
    public function specUrl(): string     { return 'https://docs.oasis-open.org/security/saml/v2.0/saml-schema-metadata-2.0.xsd'; }

    public function description(): string
    {
        return 'The generated XML metadata document must validate against the SAML 2.0 metadata schema (saml-schema-metadata-2.0.xsd).';
    }

    public function evaluate(Entity $entity): RuleResult
    {
        $schemaPath = storage_path('app/schemas/saml-schema-metadata-2.0.xsd');

        if (! file_exists($schemaPath)) {
            return RuleResult::warning(
                $this->id(),
                '[X01] XSD schema file not available — run php artisan saml:download-schemas to enable',
            );
        }

        try {
            $xml = $this->metadataService->renderXml($entity);
            $dom = new \DOMDocument();
            $dom->loadXML($xml);

            libxml_use_internal_errors(true);
            $valid        = $dom->schemaValidate($schemaPath);
            $libxmlErrors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors(false);

            if ($valid) {
                return RuleResult::pass(
                    $this->id(),
                    '[X01] XML validates against SAML2 metadata schema (saml-schema-metadata-2.0.xsd)',
                );
            }

            $firstError = $libxmlErrors[0] ?? null;
            $detail     = $firstError
                ? 'Line ' . $firstError->line . ': ' . trim($firstError->message)
                : null;

            return RuleResult::fail(
                $this->id(),
                '[X01] XML does not validate against SAML2 metadata schema',
                $detail,
            );
        } catch (\Throwable $e) {
            return RuleResult::fail($this->id(), 'Metadata schema validation failed.', $e->getMessage());
        }
    }
}
