<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Entity;
use App\Models\SchedulerSetting;
use App\Services\Entity\EntityImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use XMLReader;

/**
 * SyncEduGainMetadataJob
 *
 * Fetches the eduGAIN aggregate metadata XML and imports/updates entities
 * with source=edugain. Entities no longer present in the feed are marked inactive.
 *
 * Queue: high — long-running feed sync, must not be delayed by low-priority work.
 */
class SyncEduGainMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('high');
    }

    public function handle(): void
    {
        $url = SchedulerSetting::get('edugain_metadata_url', 'https://mds.edugain.org/edugain-v2.xml');

        Log::info('SyncEduGainMetadataJob started', ['url' => $url]);

        $response = Http::timeout(120)->get($url);

        if (! $response->successful()) {
            Log::error('SyncEduGainMetadataJob: fetch failed', ['status' => $response->status()]);
            return;
        }

        $xmlContent = $response->body();
        $seenIds    = [];
        $buffer     = [];

        $reader = new XMLReader();
        $reader->XML($xmlContent);

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'EntityDescriptor') {
                $elementXml = $reader->readOuterXml();

                try {
                    $data       = app(EntityImportService::class)->fromXml($elementXml);
                    $seenIds[]  = $data['entity_id'];
                    $buffer[]   = array_merge($data, ['source' => 'edugain', 'status' => 'active']);

                    if (count($buffer) >= 100) {
                        DB::transaction(fn () => $this->upsertBuffer($buffer));
                        $buffer = [];
                    }
                } catch (\InvalidArgumentException $e) {
                    Log::warning('SyncEduGainMetadataJob: skipping entity', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $reader->close();

        if (! empty($buffer)) {
            DB::transaction(fn () => $this->upsertBuffer($buffer));
        }

        // Suspend entities no longer present in the feed
        if (! empty($seenIds)) {
            Entity::where('source', 'edugain')
                ->whereNotIn('entity_id', $seenIds)
                ->update(['status' => 'suspended']);
        }

        Cache::put('last_run_edugain_sync', now()->timestamp, now()->addDays(30));

        Log::info('SyncEduGainMetadataJob completed', ['count' => count($seenIds)]);
    }

    private function upsertBuffer(array $buffer): void
    {
        foreach ($buffer as $data) {
            $entityData = [
                'type'                          => $data['type'],
                'status'                        => $data['status'],
                'source'                        => $data['source'],
                'registration_authority'        => $data['registration_authority'] ?? '',
                'scope'                         => $data['scope'] ?? null,
                'sp_want_assertions_signed'     => $data['sp_want_assertions_signed'] ?? true,
                'sp_want_authn_requests_signed' => $data['sp_want_authn_requests_signed'] ?? true,
                'nameid_formats'                => $data['nameid_formats'] ?? [],
                'requested_attributes'          => $data['requested_attributes'] ?? [],
            ];

            $entity = Entity::updateOrCreate(
                ['entity_id' => $data['entity_id']],
                $entityData
            );

            // Sync ui_info
            if (! empty($data['ui_info'])) {
                $entity->uiInfo()->delete();
                foreach ($data['ui_info'] as $info) {
                    $entity->uiInfo()->create($info);
                }
            }

            // Sync contacts
            if (! empty($data['contacts'])) {
                $entity->contacts()->delete();
                foreach ($data['contacts'] as $contact) {
                    $entity->contacts()->create($contact);
                }
            }

            // Sync endpoints
            if (! empty($data['endpoints'])) {
                $entity->endpoints()->delete();
                foreach ($data['endpoints'] as $endpoint) {
                    $entity->endpoints()->create($endpoint);
                }
            }
        }
    }
}
