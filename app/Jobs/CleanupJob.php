<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\EntityValidationResult;
use App\Models\SchedulerSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * CleanupJob
 *
 * Prunes stale validation results and old audit logs based on configurable
 * retention periods from SchedulerSetting. Runs weekly on Sundays.
 *
 * Queue: default
 */
class CleanupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $timeout = 300;

    public function handle(): void
    {
        $validationDays = (int) SchedulerSetting::get('cleanup_validation_days', 90);
        $auditDays      = (int) SchedulerSetting::get('cleanup_audit_days', 365);

        $pruned = EntityValidationResult::where('created_at', '<', now()->subDays($validationDays))
            ->delete();

        $archived = AuditLog::where('created_at', '<', now()->subDays($auditDays))
            ->delete();

        Cache::put('last_run_cleanup', now()->timestamp, now()->addDays(30));

        Log::info('CleanupJob completed', [
            'validation_results_pruned' => $pruned,
            'audit_logs_archived'       => $archived,
        ]);
    }
}
