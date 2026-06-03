<?php

declare(strict_types=1);

use App\Jobs\AutoGenerateMetadataJob;
use App\Jobs\CheckCertificateExpiryJob;
use App\Jobs\CleanupJob;
use App\Jobs\SchedulerHeartbeatJob;
use App\Jobs\SyncEduGainMetadataJob;
use App\Jobs\ValidateEntityMetadataJob;
use App\Models\Entity;
use App\Models\SchedulerSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Crontab entry (run as www-data or the web user):
|   * * * * * cd /var/www/federation && php artisan schedule:run >> /dev/null 2>&1
|
| All schedule times and enabled flags are configurable via the Scheduler
| settings page (/scheduler) and stored in the scheduler_settings table.
|
*/

// metadata:validate-all — dispatch ValidateEntityMetadataJob for every active entity.
Artisan::command('metadata:validate-all', function () {
    $count = 0;

    Entity::where('status', 'active')
        ->with(['certificates', 'uiInfo', 'contacts', 'endpoints', 'attributes'])
        ->each(function (Entity $entity) use (&$count) {
            ValidateEntityMetadataJob::dispatch($entity);
            $count++;
        });

    Cache::put('last_run_validate_all', now()->timestamp, now()->addDays(30));

    $this->info("Dispatched ValidateEntityMetadataJob for {$count} active entities.");
})->purpose('Dispatch metadata validation jobs for all active entities');

// certificates:check-expiry — dispatch CheckCertificateExpiryJob.
Artisan::command('certificates:check-expiry', function () {
    CheckCertificateExpiryJob::dispatch();
    $this->info('Dispatched CheckCertificateExpiryJob.');
})->purpose('Check certificate expiry and notify Admin users');

// memberships:expire-pending — reject entity_federation rows whose expires_at has passed.
Artisan::command('memberships:expire-pending', function () {
    $expired = \Illuminate\Support\Facades\DB::table('entity_federation')
        ->where('status', 'pending')
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->get();

    foreach ($expired as $row) {
        \Illuminate\Support\Facades\DB::table('entity_federation')
            ->where('entity_id', $row->entity_id)
            ->where('federation_id', $row->federation_id)
            ->update([
                'status'           => 'rejected',
                'rejection_reason' => 'Membership request expired automatically.',
                'updated_at'       => now(),
            ]);

        $entity     = \App\Models\Entity::find($row->entity_id);
        $federation = \App\Models\Federation::find($row->federation_id);

        if ($entity && $federation) {
            app(\App\Services\Notification\NotificationService::class)->dispatch(
                'entity_rejected',
                ['entity_name' => $entity->entity_id, 'reason' => 'Membership request expired automatically.'],
                $entity,
                $federation
            );
        }
    }

    $this->info("Expired {$expired->count()} pending membership(s).");
})->purpose('Reject pending federation memberships whose expiry deadline has passed');

// ── Dynamic schedule (driven by scheduler_settings table) ─────────────────

// Metadata auto-generation
if (SchedulerSetting::get('metadata_auto_generate_enabled', true)) {
    $interval = (int) SchedulerSetting::get('metadata_auto_generate_interval', 15);

    $metaTask = Schedule::job(new AutoGenerateMetadataJob())
        ->name('auto-generate-metadata')
        ->withoutOverlapping(10);

    match ($interval) {
        5       => $metaTask->everyFiveMinutes(),
        10      => $metaTask->everyTenMinutes(),
        30      => $metaTask->everyThirtyMinutes(),
        60      => $metaTask->hourly(),
        default => $metaTask->everyFifteenMinutes(),
    };
}

// Entity validation
if (SchedulerSetting::get('validation_auto_enabled', true)) {
    $day  = SchedulerSetting::get('validation_schedule_day', 'sunday');
    $time = SchedulerSetting::get('validation_schedule_time', '03:00');

    $validTask = Schedule::command('metadata:validate-all')->name('validate-all');

    match ($day) {
        'monday'    => $validTask->weekly()->mondays()->at($time),
        'tuesday'   => $validTask->weekly()->tuesdays()->at($time),
        'wednesday' => $validTask->weekly()->wednesdays()->at($time),
        'thursday'  => $validTask->weekly()->thursdays()->at($time),
        'friday'    => $validTask->weekly()->fridays()->at($time),
        'saturday'  => $validTask->weekly()->saturdays()->at($time),
        'daily'     => $validTask->dailyAt($time),
        default     => $validTask->weekly()->sundays()->at($time),
    };
}

// Certificate expiry check
if (SchedulerSetting::get('cert_check_enabled', true)) {
    $time = SchedulerSetting::get('cert_check_time', '06:00');
    Schedule::command('certificates:check-expiry')
        ->name('cert-expiry-check')
        ->dailyAt($time);
}

// Pending membership expiry — runs daily at 07:00
Schedule::command('memberships:expire-pending')
    ->name('memberships-expire-pending')
    ->dailyAt('07:00');

// eduGAIN sync
if (SchedulerSetting::get('edugain_sync_enabled', false)) {
    $hours = (int) SchedulerSetting::get('edugain_sync_interval_hours', 6);
    Schedule::job(new SyncEduGainMetadataJob())
        ->cron("0 */{$hours} * * *")
        ->name('edugain-sync')
        ->environments(['production']);
}

// Cleanup
if (SchedulerSetting::get('cleanup_enabled', true)) {
    Schedule::job(new CleanupJob())
        ->weekly()->sundays()->at('04:00')
        ->name('cleanup');
}

// Horizon snapshot — always on
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Scheduler heartbeat — always on; used by SchedulerHeartbeatCheck
Schedule::job(new SchedulerHeartbeatJob())->everyMinute()->name('scheduler-heartbeat');
