<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Entity;
use App\Services\Entity\EntityMetadataService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * MetadataPreview Livewire component
 *
 * Renders the SAML2 EntityDescriptor XML for a given entity and runs
 * REFEDS/structural validation checks.  Displayed as a two-panel card:
 *   - Left  : validation check results (pass / warning / error)
 *   - Right : formatted XML in a scrollable <pre> block
 *
 * When $entity is null the component shows only a live search box so the
 * user can find an entity to preview (used on the metadata index page).
 *
 * Note: do NOT rename any method to validate() — conflicts with Livewire base class.
 */
class MetadataPreview extends Component
{
    public ?Entity $entity = null;

    // Live search
    public string $entitySearch = '';

    // Validation state
    public bool  $hasValidated   = false;
    public array $checks         = [];
    public bool  $overallPassed  = false;

    // XML state
    public string $xml           = '';
    public bool   $xmlError      = false;
    public string $xmlErrorMsg   = '';

    // Loading flag for "Validate Again" button
    public bool $validating      = false;

    public function mount(?Entity $entity = null): void
    {
        $this->entity = $entity;

        if ($this->entity !== null) {
            $this->renderXml();
            $this->runValidation(false);
        }
    }


    #[Computed]
    public function matchedEntities(): Collection
    {
        if (strlen($this->entitySearch) < 2) {
            return collect();
        }

        return Entity::query()
            ->with('uiInfo')
            ->where('entity_id', 'like', '%' . $this->entitySearch . '%')
            ->orWhereHas('uiInfo', fn($q) => $q
                ->where('field', 'display_name')
                ->where('value', 'like', '%' . $this->entitySearch . '%'))
            ->limit(10)
            ->get();
    }

    public function selectEntity(string $id): void
    {
        $this->entity       = Entity::find($id);
        $this->entitySearch = '';
        $this->hasValidated = false;
        $this->checks       = [];
        $this->overallPassed  = false;
        $this->xml          = '';
        $this->xmlError     = false;
        $this->xmlErrorMsg  = '';

        if ($this->entity !== null) {
            $this->renderXml();
            $this->runValidation(false);
        }
    }


    private function renderXml(): void
    {
        if ($this->entity === null) {
            return;
        }

        try {
            /** @var EntityMetadataService $service */
            $service   = app(EntityMetadataService::class);
            $this->xml = $service->renderXml($this->entity);
            $this->xmlError    = false;
            $this->xmlErrorMsg = '';
        } catch (\Throwable $e) {
            $this->xml         = '';
            $this->xmlError    = true;
            $this->xmlErrorMsg = $e->getMessage();
            Log::warning('MetadataPreview: XML render failed', [
                'entity_id' => $this->entity->entity_id,
                'error'     => $e->getMessage(),
            ]);
        }
    }


    /**
     * Run EntityMetadataService::validate() and update component state.
     *
     * @param  bool  $notify  Pass false on initial mount to suppress the flash notification
     *                        before the user has interacted with the page.
     */
    public function runValidation(bool $notify = true): void
    {
        if ($this->entity === null) {
            return;
        }

        $this->validating = true;

        try {
            /** @var EntityMetadataService $service */
            $service = app(EntityMetadataService::class);
            $result  = $service->validate($this->entity);

            $this->checks        = $result->checks();
            $this->overallPassed = $result->passed();
            $this->hasValidated  = true;
        } catch (\Throwable $e) {
            $this->checks        = [];
            $this->overallPassed = false;
            $this->hasValidated  = true;
            Log::warning('MetadataPreview: validation failed', [
                'entity_id' => $this->entity->entity_id,
                'error'     => $e->getMessage(),
            ]);
        } finally {
            $this->validating = false;
        }

        if ($notify) {
            $this->dispatch('notify',
                type: $this->overallPassed ? 'success' : 'error',
                message: $this->overallPassed ? 'Validation passed.' : 'Validation failed: issues found.');
        }
    }


    /**
     * Stream the rendered XML as a file download. Sanitizes the entity_id into a safe filename.
     * Uses streamDownload (no temp file created on disk).
     */
    public function downloadXml(): mixed
    {
        if ($this->entity === null || empty($this->xml)) {
            session()->flash('error', 'No XML to download.');
            return null;
        }

        $filename = preg_replace('/[^a-zA-Z0-9\-_.]/', '_', $this->entity->entity_id) . '.xml';

        return response()->streamDownload(function () {
            echo $this->xml;
        }, $filename, ['Content-Type' => 'application/xml']);
    }


    #[Computed]
    public function errorChecks(): array
    {
        return array_filter($this->checks, fn($c) => in_array($c['status'], ['error', 'fail'], true));
    }

    #[Computed]
    public function warningChecks(): array
    {
        return array_filter($this->checks, fn($c) => $c['status'] === 'warning');
    }

    #[Computed]
    public function passedChecks(): array
    {
        return array_filter($this->checks, fn($c) => $c['status'] === 'pass');
    }

    public function checkIcon(string $status): string
    {
        return match($status) {
            'pass'    => 'bi-check-circle-fill text-success',
            'warning' => 'bi-exclamation-triangle-fill text-warning',
            'error',
            'fail'    => 'bi-x-circle-fill text-danger',
            default   => 'bi-dash-circle text-muted',
        };
    }

    public function render(): View
    {
        return view('livewire.metadata-preview');
    }
}
