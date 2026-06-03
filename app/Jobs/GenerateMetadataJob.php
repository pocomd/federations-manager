<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exceptions\XmlSigningException;
use App\Models\Entity;
use App\Models\Federation;
use App\Models\SchedulerSetting;
use App\Services\Entity\EntityMetadataService;
use App\Services\Signing\SigningDriverFactory;
use App\Services\Webhook\WebhookService;
use DOMDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * GenerateMetadataJob
 *
 * Builds and signs the aggregate SAML2 metadata for a federation.
 * Pass eduGainOnly=true to generate the subset for eduGAIN export.
 *
 * Cache keys:
 *   Full aggregate : "federation_metadata:{id}"
 *   eduGAIN subset : "federation_edugain_metadata:{id}"
 */
class GenerateMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $federationId,
        public readonly bool $eduGainOnly = false,
    ) {}

    public function handle(EntityMetadataService $service, SigningDriverFactory $signingFactory): void
    {
        $federation = Federation::findOrFail($this->federationId);

        $cacheKey = $this->eduGainOnly
            ? "federation_edugain_metadata:{$federation->id}"
            : "federation_metadata:{$federation->id}";

        // Default 7 days (168 h) — gives operators time to diagnose and fix
        // generation issues before metadata expires in consumers' caches.
        $hours = (int) SchedulerSetting::get('metadata_valid_until_hours', 168);

        // ── Fetch active member entities with entity-level status guard ───────
        $entities = $federation->entities()
            ->wherePivot('status', 'active')
            ->where('entities.status', 'active')
            ->when($this->eduGainOnly, fn($q) => $q->where('entities.edugain', true))
            ->with(['certificates', 'uiInfo', 'contacts', 'endpoints', 'attributes', 'federations'])
            ->get();

        // ── eduGAIN eligibility gate ──────────────────────────────────────────
        if ($this->eduGainOnly) {
            $entities = $entities->filter(function (Entity $entity) {
                if ($entity->certificates->isEmpty()) {
                    Log::warning('Excluding eduGAIN entity — no certificates', [
                        'entity_id' => $entity->entity_id,
                    ]);
                    return false;
                }
                if (! $entity->hasSecurityContact()) {
                    Log::warning('Excluding eduGAIN entity — no security contact (SIRTFI mandatory)', [
                        'entity_id' => $entity->entity_id,
                    ]);
                    return false;
                }
                return true;
            })->values();
        }

        // ── Build <md:EntitiesDescriptor> aggregate ───────────────────────────
        $xml = $this->buildEntitiesDescriptor($entities, $service, $hours, $federation);

        // ── Sign via the federation's configured driver (non-fatal if unconfigured) ──
        try {
            $xml = $signingFactory->make($federation)->sign($xml, $federation);
        } catch (XmlSigningException $e) {
            Log::warning('Federation metadata signing skipped — driver not configured or failed', [
                'federation_id' => $federation->id,
                'edugain_only'  => $this->eduGainOnly,
                'driver'        => $federation->signing_driver,
                'error'         => $e->getMessage(),
            ]);
        }

        // ── Cache for configured hours ────────────────────────────────────────
        Cache::put($cacheKey, $xml, now()->addHours($hours));

        // ── Write to disk (storage/app/metadata/{slug}/) ─────────────────────
        $path = $federation->metadataPath($this->eduGainOnly);
        $dir  = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($path, $xml);

        if ($this->eduGainOnly) {
            $federation->update(['metadata_edugain_generated_at' => now()]);
        } else {
            $federation->update(['metadata_generated_at' => now()]);

            app(\App\Services\Notification\NotificationService::class)->dispatch(
                'metadata_generated',
                ['federation_name' => $federation->name],
                null,
                $federation
            );

            app(WebhookService::class)->dispatch('metadata.generated', [
                'federation_id' => $federation->id,
                'federation_uri' => $federation->uri,
            ]);
        }

        Cache::forget($this->errorCacheKey());

        Log::info('Federation metadata generated', [
            'federation_id'  => $federation->id,
            'edugain_only'   => $this->eduGainOnly,
            'entity_count'   => $entities->count(),
            'cache_hours'    => $hours,
            'signed'         => str_contains($xml, 'ds:Signature'),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Cache::put($this->errorCacheKey(), $e->getMessage(), now()->addHours(24));
    }

    private function errorCacheKey(): string
    {
        return $this->eduGainOnly
            ? "federation_edugain_metadata_error:{$this->federationId}"
            : "federation_metadata_error:{$this->federationId}";
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function buildEntitiesDescriptor(
        \Illuminate\Support\Collection $entities,
        EntityMetadataService $service,
        int $hours,
        Federation $federation,
    ): string {
        $dom  = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $now        = now()->utc();
        $id         = 'LEAF-' . $now->format('YmdHi');
        $createdAt  = $now->format('Y-m-d\TH:i:s\Z');
        $validUntil = $now->copy()->addHours($hours)->format('Y-m-d\TH:i:s\Z');

        $nsMap = [
            'xmlns:md'     => 'urn:oasis:names:tc:SAML:2.0:metadata',
            'xmlns:mdui'   => 'urn:oasis:names:tc:SAML:metadata:ui',
            'xmlns:mdrpi'  => 'urn:oasis:names:tc:SAML:metadata:rpi',
            'xmlns:mdattr' => 'urn:oasis:names:tc:SAML:metadata:attribute',
            'xmlns:saml'   => 'urn:oasis:names:tc:SAML:2.0:assertion',
            'xmlns:shibmd' => 'urn:mace:shibboleth:metadata:1.0',
            'xmlns:ds'     => 'http://www.w3.org/2000/09/xmldsig#',
        ];

        $root = $dom->createElementNS('urn:oasis:names:tc:SAML:2.0:metadata', 'md:EntitiesDescriptor');

        foreach ($nsMap as $prefix => $uri) {
            $root->setAttribute($prefix, $uri);
        }

        $root->setAttribute('ID',         $id);
        $root->setAttribute('Name',       $federation->uri);
        $root->setAttribute('validUntil', $validUntil);

        $dom->appendChild($root);

        // md:Extensions — mdrpi:PublicationInfo (MDRPI §2.3; required for eduGAIN)
        $extensions = $dom->createElementNS('urn:oasis:names:tc:SAML:2.0:metadata', 'md:Extensions');
        $root->appendChild($extensions);

        $pubInfo = $dom->createElementNS('urn:oasis:names:tc:SAML:metadata:rpi', 'mdrpi:PublicationInfo');
        $pubInfo->setAttribute('publisher',       $federation->uri);
        $pubInfo->setAttribute('creationInstant', $createdAt);
        $extensions->appendChild($pubInfo);

        if ($this->eduGainOnly) {
            $policy = $dom->createElementNS('urn:oasis:names:tc:SAML:metadata:rpi', 'mdrpi:UsagePolicy');
            $policy->setAttribute('xml:lang', 'en');
            $policy->appendChild($dom->createTextNode('http://www.edugain.org/policy/metadata-tou_1_0.txt'));
            $pubInfo->appendChild($policy);
        }

        foreach ($entities as $entity) {
            /** @var Entity $entity */
            try {
                $entityXml = $service->renderXml($entity);

                $entityDom = new DOMDocument();
                $entityDom->loadXML($entityXml);

                $imported = $dom->importNode($entityDom->documentElement, true);
                $root->appendChild($imported);
            } catch (\Throwable $e) {
                Log::warning('Skipping entity in metadata aggregate — render failed', [
                    'entity_id' => $entity->entity_id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        $xml = $dom->saveXML();

        if ($xml === false) {
            throw new \RuntimeException('Failed to serialise EntitiesDescriptor DOM document.');
        }

        return $xml;
    }
}
