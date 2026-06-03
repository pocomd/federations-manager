<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Jobs\GenerateMetadataJob;
use App\Models\Federation;
use App\Models\SchedulerSetting;
use App\Services\Signing\SigningDriverFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class FederationMetadataSign extends Component
{
    public Federation $federation;

    public function mount(int|string $federationId): void
    {
        $this->federation = Federation::findOrFail($federationId);
    }

    /** @authorizes metadata.generate */
    public function sign(): void
    {
        Gate::authorize('metadata.generate');

        try {
            GenerateMetadataJob::dispatchSync($this->federation->id);
            GenerateMetadataJob::dispatchSync($this->federation->id, true);
        } catch (\Throwable $e) {
            session()->flash('error', 'Metadata generation failed: ' . $e->getMessage());
            return;
        }

        $this->federation->refresh();
        session()->flash('success', 'Metadata signed successfully.');
    }

    public function recheck(): void
    {
        $this->federation->refresh();
    }

    public function render(): View
    {
        $driver  = app(SigningDriverFactory::class)->make($this->federation);
        $hasKey  = $driver->hasKey($this->federation);
        $hasCert = $driver->hasCert($this->federation);
        $canSign = $hasKey && $hasCert;

        $signingKeyLabel = $canSign ? 'federation key' : null;

        $entityCount          = $this->federation->entities()->count();
        $validUntilHours      = (int) SchedulerSetting::get('metadata_valid_until_hours', 168);
        $autoGenerateEnabled  = (bool) SchedulerSetting::get('metadata_auto_generate_enabled', true);
        $autoGenerateInterval = (int) SchedulerSetting::get('metadata_auto_generate_interval', 15);

        $validUntil = $this->federation->metadata_generated_at
            ? $this->federation->metadata_generated_at->addHours($validUntilHours)
            : null;

        // Compute next clock-aligned scheduler slot (*/N * * * *) so the
        // time is always in the future rather than showing "due".
        if ($autoGenerateEnabled) {
            $now         = now();
            $intervalSec = $autoGenerateInterval * 60;
            $secInHour   = $now->minute * 60 + $now->second;
            $nextSlotSec = (int) (ceil(($secInHour + 1) / $intervalSec) * $intervalSec);
            $nextSignAt  = $now->copy()->startOfHour()->addSeconds($nextSlotSec);
        } else {
            $nextSignAt = null;
        }

        $metadataError = Cache::get("federation_metadata_error:{$this->federation->id}");
        $eduGainError  = Cache::get("federation_edugain_metadata_error:{$this->federation->id}");

        // Stale if auto-generate is enabled but last run is older than 3× the interval.
        // This catches queue workers being down without a job failure being recorded.
        $staleThreshold = $autoGenerateInterval * 3;
        $metadataStale  = $autoGenerateEnabled
            && $this->federation->metadata_generated_at !== null
            && $this->federation->metadata_generated_at->diffInMinutes(now()) > $staleThreshold;
        $eduGainStale   = $autoGenerateEnabled
            && $this->federation->metadata_edugain_generated_at !== null
            && $this->federation->metadata_edugain_generated_at->diffInMinutes(now()) > $staleThreshold;

        return view('livewire.federation-metadata-sign', [
            'canSign'              => $canSign,
            'signingKeyLabel'      => $signingKeyLabel,
            'entityCount'          => $entityCount,
            'validUntilHours'      => $validUntilHours,
            'validUntil'           => $validUntil,
            'nextSignAt'           => $nextSignAt,
            'autoGenerateInterval' => $autoGenerateInterval,
            'metadataError'        => $metadataError,
            'eduGainError'         => $eduGainError,
            'metadataStale'        => $metadataStale,
            'eduGainStale'         => $eduGainStale,
            'staleThreshold'       => $staleThreshold,
        ]);
    }
}
