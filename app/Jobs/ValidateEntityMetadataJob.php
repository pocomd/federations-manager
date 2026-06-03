<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Entity;
use App\Models\EntityValidationResult;
use App\Services\Entity\EntityMetadataService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ValidateEntityMetadataJob
 *
 * Runs all structural, certificate and REFEDS compliance checks on a single entity
 * and persists the result to entity_validation_results.
 *
 * Dispatched:
 *   - By the weekly scheduler (metadata:validate-all Artisan command, Sundays 03:00)
 *   - On-demand via EntityMetadataController::validateEntity()
 *
 * Queue: low — batch validation runs are low-priority background work.
 */
class ValidateEntityMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    public function __construct(
        public readonly Entity $entity,
    ) {
        $this->onQueue('low');
    }

    public function handle(EntityMetadataService $service): void
    {
        // Load all relationships needed by validate() in one query
        $this->entity->loadMissing(['certificates', 'uiInfo', 'contacts', 'endpoints', 'attributes']);

        $result = $service->validate($this->entity);

        // EntityMetadataService::validate() already persists the result when
        // the entity exists in the DB — log the outcome here for observability.
        Log::info('Entity metadata validated', [
            'entity_id' => $this->entity->entity_id,
            'passed'    => $result->passed(),
            'errors'    => count($result->errors()),
            'warnings'  => count($result->warnings()),
        ]);
    }
}
