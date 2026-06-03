<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Entity;
use App\Models\EntityAttribute;
use App\Models\EntityUiInfo;
use App\Services\Entity\EntityMetadataService;
use App\Services\Entity\CertificateService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Support\Facades\Auth;

/**
 * EntitySearch Livewire component
 *
 * Renders the entity list with live filtering, sorting, and
 * inline metadata validation. Wired to EntityController.
 *
 * Filter criteria map to SAML / REFEDS attributes:
 *   search       → entity_id, name_en, org_name_en (free-text)
 *   type         → IDPSSODescriptor ('idp') vs SPSSODescriptor ('sp')
 *   status       → entity lifecycle state (draft/pending/active/suspended/deleted)
 *   edugain      → entities exported to eduGAIN interfederation
 *   federation   → entities belonging to a specific federation
 *   sirtfi       → entities asserting REFEDS SIRTFI compliance
 *   rs           → entities asserting R&S entity category
 *   coco         → entities asserting Code of Conduct v2
 *   cert_expiry  → certificates expiring within N days
 *   valid_only   → entities that passed last metadata validation
 */
class EntitySearch extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'bootstrap';

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $type = '';           // '' | 'idp' | 'sp'

    #[Url]
    public string $status = '';         // '' | 'draft' | 'pending' | 'active' | 'suspended'

    #[Url]
    public string $federation = '';     // federation id

    #[Url]
    public bool $edugain = false;

    #[Url]
    public bool $sirtfi = false;

    #[Url]
    public bool $rs = false;

    #[Url]
    public bool $coco = false;

    #[Url]
    public int $certExpiry = 0;         // 0 = no filter, 14|30|60|90 = expiry within N days

    #[Url]
    public bool $validOnly = false;

    #[Url]
    public string $sortBy = 'entity_id';

    #[Url]
    public string $sortDir = 'asc';

    public int $perPage = 25;

    public array   $validationResults  = [];   // keyed by entity->id
    public array   $validating         = [];   // entity ids currently being validated
    public ?string $expandedEntityId   = null; // which row is expanded (UUID)

    public Collection $federations;

    public function mount(): void
    {
        $this->federations = \App\Models\Federation::orderBy('name')
            ->get(['id', 'name']);
    }


    public function updatedSearch():    void { $this->resetPage(); }
    public function updatedType():      void { $this->resetPage(); }
    public function updatedStatus():    void { $this->resetPage(); }
    public function updatedFederation():void { $this->resetPage(); }
    public function updatedEdugain():   void { $this->resetPage(); }
    public function updatedSirtfi():    void { $this->resetPage(); }
    public function updatedRs():        void { $this->resetPage(); }
    public function updatedCoco():      void { $this->resetPage(); }
    public function updatedCertExpiry():void { $this->resetPage(); }
    public function updatedValidOnly(): void { $this->resetPage(); }


    public function toggleType(string $value): void
    {
        $this->type = $this->type === $value ? '' : $value;
        $this->resetPage();
    }

    public function toggleCertExpiry(): void
    {
        $this->certExpiry = $this->certExpiry === 14 ? 0 : 14;
        $this->resetPage();
    }

    public function toggleEdugain(): void
    {
        $this->edugain = !$this->edugain;
        $this->resetPage();
    }


    private function managedFederationIds(): ?object
    {
        $user = Auth::user();
        if (!$user->hasRole('Federation Manager')) {
            return null;
        }
        return $user->managedFederations()->pluck('federations.id');
    }

    #[Computed]
    public function entities(): LengthAwarePaginator
    {
        $sirtfiUri      = EntityAttribute::URI_SIRTFI;
        $managedIds     = $this->managedFederationIds();

        return Entity::query()
            ->with(['certificates', 'contacts', 'federations:id,name', 'uiInfo'])
            ->when($managedIds, fn($q) => $q->where(fn($q) => $q
                ->whereHas('federations', fn($q) => $q->whereIn('federations.id', $managedIds))
                ->orWhereDoesntHave('federations')
            ))
            ->when($this->search, function ($q) {
                $q->where(function ($q) {
                    $q->where('entity_id', 'like', "%{$this->search}%")
                      ->orWhereHas('uiInfo', fn($q) => $q
                          ->where('value', 'like', "%{$this->search}%")
                          ->whereIn('field', ['display_name', 'org_name']));
                });
            })
            ->when($this->type,       fn($q) => $q->where('type',    $this->type))
            ->when($this->status,     fn($q) => $q->where('status',  $this->status))
            ->when($this->edugain,    fn($q) => $q->where('edugain', true))
            ->when($this->sirtfi,     fn($q) => $q->whereHas('attributes', fn($q) => $q
                ->where('attribute_name',  EntityAttribute::ATTR_ASSURANCE_PROFILE)
                ->where('attribute_value', $sirtfiUri)))
            ->when($this->rs,         fn($q) => $q->whereHas('attributes', fn($q) => $q
                ->where('attribute_name',  EntityAttribute::ATTR_ENTITY_CATEGORY)
                ->where('attribute_value', EntityAttribute::URI_RS)))
            ->when($this->coco,       fn($q) => $q->whereHas('attributes', fn($q) => $q
                ->where('attribute_name',  EntityAttribute::ATTR_ENTITY_CATEGORY)
                ->where('attribute_value', EntityAttribute::URI_COCO_V2)))
            ->when($this->federation, fn($q) => $q->whereHas('federations',
                fn($q) => $q->where('federations.id', $this->federation)))
            ->when($this->certExpiry > 0, fn($q) => $q->whereHas('certificates',
                fn($q) => $q->where('not_after', '<=', now()->addDays($this->certExpiry))))
            ->when($this->validOnly,  fn($q) => $q->whereHas('currentValidation',
                fn($q) => $q->where('passed', true)))
            ->when($this->sortBy === 'name_en', function ($q) {
                $sub = EntityUiInfo::where('field', 'display_name')
                    ->where('lang', 'en')
                    ->select('entity_id as ui_entity_id', 'value as name_en');
                $q->leftJoinSub($sub, 'ui_name', 'entities.id', '=', 'ui_name.ui_entity_id')
                  ->select('entities.*')
                  ->orderBy('ui_name.name_en', $this->sortDir);
            }, fn($q) => $q->orderBy($this->sortBy, $this->sortDir))
            ->paginate($this->perPage);
    }


    /**
     * Entity counts for the stats bar. FM-scoped runs without cache (each FM sees a different
     * subset). Admin-scoped result is cached for 5 min under 'entity_summary_counts'.
     */
    #[Computed]
    public function summaryCounts(): array
    {
        $managedIds = $this->managedFederationIds();

        $scope = fn() => Entity::when($managedIds, fn($q) => $q->where(fn($q) => $q
            ->whereHas('federations', fn($q) => $q->whereIn('federations.id', $managedIds))
            ->orWhereDoesntHave('federations')
        ));

        if ($managedIds !== null) {
            return [
                'total'        => $scope()->count(),
                'idp'          => $scope()->where('type', 'idp')->count(),
                'sp'           => $scope()->where('type', 'sp')->count(),
                'active'       => $scope()->where('status', 'active')->count(),
                'draft'        => $scope()->where('status', 'draft')->count(),
                'edugain'      => $scope()->where('edugain', true)->count(),
                'sirtfi'       => $scope()->whereHas('attributes', fn($q) => $q
                    ->where('attribute_name', EntityAttribute::ATTR_ASSURANCE_PROFILE)
                    ->where('attribute_value', EntityAttribute::URI_SIRTFI))->count(),
                'cert_critical' => $scope()->whereHas('certificates', fn($q) => $q
                    ->where('not_after', '<=', now()->addDays(14)))->count(),
            ];
        }

        return Cache::remember('entity_summary_counts', 300, function () {
            return [
                'total'   => Entity::count(),
                'idp'     => Entity::where('type', 'idp')->count(),
                'sp'      => Entity::where('type', 'sp')->count(),
                'active'  => Entity::where('status', 'active')->count(),
                'draft'   => Entity::where('status', 'draft')->count(),
                'edugain' => Entity::where('edugain', true)->count(),
                'sirtfi'  => Entity::whereHas('attributes', fn($q) => $q
                    ->where('attribute_name', EntityAttribute::ATTR_ASSURANCE_PROFILE)
                    ->where('attribute_value', EntityAttribute::URI_SIRTFI))->count(),
                'cert_critical' => Entity::whereHas('certificates', fn($q) => $q
                    ->where('not_after', '<=', now()->addDays(14)))->count(),
            ];
        });
    }


    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy  = $column;
            $this->sortDir = 'asc';
        }
    }


    /**
     * Run EntityMetadataService::validate() for a single entity and persist the result.
     * Busts the metadata_validation cache before running to force a fresh check.
     * Auto-expands the row when validation finds issues.
     *
     * @sideeffects  entity.metadata_valid + entity.metadata_validated_at updated
     */
    public function validateEntity(string $entityId): void
    {
        $this->validating[$entityId] = true;

        $entity = Entity::with('certificates')->findOrFail($entityId);

        // Reuse the same check logic from the controller
        // In production this delegates to EntityMetadataService
        $cacheKey = "metadata_validation:{$entityId}";
        Cache::forget($cacheKey);

        $result = app(\App\Services\Entity\EntityMetadataService::class)
            ->validate($entity)
            ->toArray();

        $this->validationResults[$entityId] = $result;
        unset($this->validating[$entityId]);

        if (!empty($result['errors'] ?? []) || !empty($result['warnings'] ?? [])) {
            $this->expandedEntityId = $entityId;
        }

        $passed = $result['passed'] ?? false;
        $this->dispatch('notify',
            type: $passed ? 'success' : 'info',
            message: $passed ? 'Validation passed.' : 'Validation complete: issues found.');

        // Update the entity's validation state
        $entity->update([
            'metadata_valid'        => $result['passed'],
            'metadata_validated_at' => now(),
        ]);
    }

    public function toggleExpand(string $entityId): void
    {
        $this->expandedEntityId = $this->expandedEntityId === $entityId
            ? null
            : $entityId;
    }


    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->type !== ''
            || $this->status !== ''
            || $this->federation !== ''
            || $this->edugain
            || $this->sirtfi
            || $this->rs
            || $this->coco
            || $this->certExpiry !== 0
            || $this->validOnly;
    }

    public function countActiveFilters(): int
    {
        return collect([
            $this->search !== '',
            $this->type !== '',
            $this->status !== '',
            $this->federation !== '',
            $this->edugain,
            $this->sirtfi,
            $this->rs,
            $this->coco,
            $this->certExpiry !== 0,
            $this->validOnly,
        ])->filter()->count();
    }


    public function resetFilters(): void
    {
        $this->reset(['search', 'type', 'status', 'federation',
                       'edugain', 'sirtfi', 'rs', 'coco',
                       'certExpiry', 'validOnly']);
        $this->resetPage();
    }

    /**
     * @listens  entity-suspended, entity-reactivated
     */
    #[On('entity-suspended')]
    #[On('entity-reactivated')]
    public function handleEntityStatusChanged(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.entity-search');
    }
}
