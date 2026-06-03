<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\EntityCertificate;
use App\Models\Federation;
use App\Services\Auth\FederationScopeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StatisticsController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        Gate::authorize('compliance.view');

        $scope = app(FederationScopeService::class);

        $stats = $scope->isConstrained()
            ? $this->buildStats($scope->ids())
            : Cache::remember('registry_statistics', 1800, fn () => $this->buildStats(null));

        return view('statistics.index', $stats);
    }

    private function buildStats(?iterable $managedIds): array
    {
        $entityScope = fn() => Entity::when($managedIds, fn($q) => $q->whereHas('federations', fn($q) => $q->whereIn('federations.id', $managedIds)));
        $certScope   = fn() => EntityCertificate::when($managedIds, fn($q) => $q->whereHas('entity', fn($q) => $q->whereHas('federations', fn($q) => $q->whereIn('federations.id', $managedIds))));

        $federations = Federation::where('status', 'active')
            ->when($managedIds, fn($q) => $q->whereIn('id', $managedIds))
            ->withCount([
                'entities as total_count',
                'entities as active_count' => fn($q) => $q->where('entity_federation.status', 'active'),
            ])
            ->get();

        return [
            'totalEntities'     => $entityScope()->count(),
            'activeEntities'    => $entityScope()->where('status', 'active')->count(),
            'pendingEntities'   => $entityScope()->where('status', 'pending')->count(),
            'criticalCerts'     => $certScope()->whereDate('not_after', '<=', now()->addDays(14))->count(),
            'registrationTrend' => $entityScope()->select(DB::raw('DATE_FORMAT(created_at,"%Y-%m") as month, count(*) as total'))
                ->groupBy('month')
                ->orderBy('month')
                ->take(12)
                ->get()
                ->toArray(),
            'federations'       => $federations->toArray(),
            'federationCount'   => $federations->count(),
        ];
    }

    public function exportEntities(): StreamedResponse
    {
        Gate::authorize('compliance.view');

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['entity_id', 'type', 'status', 'display_name', 'source', 'created_at']);

            Entity::with('uiInfo')->orderBy('entity_id')->cursor()->each(function ($entity) use ($out) {
                fputcsv($out, [
                    $entity->entity_id,
                    $entity->type,
                    $entity->status,
                    $entity->uiInfo->where('field', 'display_name')->where('lang', 'en')->first()?->value ?? '',
                    $entity->source,
                    $entity->created_at,
                ]);
            });

            fclose($out);
        }, 'entities-' . date('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportCertificates(): StreamedResponse
    {
        Gate::authorize('compliance.view');

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['entity_id', 'use', 'not_before', 'not_after', 'subject', 'issuer']);

            EntityCertificate::with('entity')->orderBy('not_after')->cursor()->each(function ($cert) use ($out) {
                fputcsv($out, [
                    $cert->entity?->entity_id ?? '',
                    $cert->use,
                    $cert->not_before,
                    $cert->not_after,
                    $cert->subject ?? '',
                    $cert->issuer ?? '',
                ]);
            });

            fclose($out);
        }, 'certificates-' . date('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportMemberships(): StreamedResponse
    {
        Gate::authorize('compliance.view');

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['entity_id', 'federation_name', 'membership_status', 'approved_at']);

            DB::table('entity_federation')
                ->join('entities', 'entities.id', '=', 'entity_federation.entity_id')
                ->join('federations', 'federations.id', '=', 'entity_federation.federation_id')
                ->select('entities.entity_id', 'federations.name', 'entity_federation.status', 'entity_federation.approved_at')
                ->orderBy('entities.entity_id')
                ->cursor()
                ->each(function ($row) use ($out) {
                    fputcsv($out, [$row->entity_id, $row->name, $row->status, $row->approved_at]);
                });

            fclose($out);
        }, 'memberships-' . date('Y-m-d') . '.csv', ['Content-Type' => 'text/csv']);
    }
}
