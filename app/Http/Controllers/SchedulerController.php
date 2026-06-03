<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SchedulerSettingsRequest;
use App\Jobs\AutoGenerateMetadataJob;
use App\Jobs\CheckCertificateExpiryJob;
use App\Jobs\CleanupJob;
use App\Jobs\SyncEduGainMetadataJob;
use App\Models\AuditLog;
use App\Models\SchedulerSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class SchedulerController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        Gate::authorize('federation.create');

        $settings = SchedulerSetting::orderBy('group')->orderBy('key')->get()->groupBy('group');

        $ts = static fn (int|string|null $v): ?Carbon => $v ? Carbon::createFromTimestamp((int) $v) : null;

        $lastRuns = [
            'metadata'   => $ts(Cache::get('last_run_auto_generate_metadata')),
            'validate'   => $ts(Cache::get('last_run_validate_all')),
            'cert-check' => $ts(Cache::get('last_run_cert_expiry_check')),
            'edugain'    => $ts(Cache::get('last_run_edugain_sync')),
            'cleanup'    => $ts(Cache::get('last_run_cleanup')),
        ];

        return view('scheduler.index', compact('settings', 'lastRuns'));
    }

    public function update(SchedulerSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        foreach ($validated as $key => $value) {
            SchedulerSetting::set($key, $value);
        }

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => null,
            'action'     => 'scheduler_settings_updated',
            'old_values' => null,
            'new_values' => $validated,
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Scheduler settings saved.');
    }

    /**
     * Immediately dispatch a named scheduler job outside the normal schedule.
     *
     * The 'validate' job runs Artisan synchronously; all others are dispatched to the queue.
     *
     * @dispatches  AutoGenerateMetadataJob | CheckCertificateExpiryJob | SyncEduGainMetadataJob | CleanupJob (queued)
     *              Artisan::call('metadata:validate-all') for 'validate' (synchronous)
     */
    public function runNow(Request $request, string $job): RedirectResponse
    {
        Gate::authorize('federation.create');

        match ($job) {
            'metadata'   => AutoGenerateMetadataJob::dispatch(),
            'validate'   => Artisan::call('metadata:validate-all'),
            'cert-check' => CheckCertificateExpiryJob::dispatch(),
            'edugain'    => SyncEduGainMetadataJob::dispatch(),
            'cleanup'    => CleanupJob::dispatch(),
            default      => abort(404),
        };

        AuditLog::create([
            'user_id'    => Auth::id(),
            'entity_id'  => null,
            'action'     => "scheduler_job_run_now:{$job}",
            'old_values' => null,
            'new_values' => ['job' => $job],
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', "Job \"{$job}\" dispatched.");
    }
}
