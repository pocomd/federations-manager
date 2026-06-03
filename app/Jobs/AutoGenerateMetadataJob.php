<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Federation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * AutoGenerateMetadataJob
 *
 * Iterates all active federations and dispatches GenerateMetadataJob for each.
 * Dispatched by the scheduler on the configured interval.
 *
 * Queue: high — triggers all per-federation signing jobs, must not be delayed.
 */
class AutoGenerateMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 60;

    public function __construct()
    {
        $this->onQueue('high');
    }

    public function handle(): void
    {
        $federations = Federation::where('status', 'active')->get();

        foreach ($federations as $federation) {
            GenerateMetadataJob::dispatch($federation->id);
            GenerateMetadataJob::dispatch($federation->id, true);
        }

        Cache::put('last_run_auto_generate_metadata', now()->timestamp, now()->addDays(30));

        Log::info('AutoGenerateMetadataJob dispatched', [
            'federations' => $federations->count(),
        ]);
    }
}
