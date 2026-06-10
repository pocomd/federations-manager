<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\GenerateMetadataJob;
use App\Models\EntityValidationResult;
use App\Models\Federation;
use App\Models\SchedulerSetting;
use App\Services\Auth\FederationScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class MetadataGenerationController extends Controller
{
    public function index(Request $request): \Illuminate\View\View
    {
        Gate::authorize('metadata.view');

        $scope = app(FederationScopeService::class);
        $scope->resolve($request->user());

        $federations = $scope->scopeQuery(
            Federation::withCount([
                'entities as active_entity_count' => function ($q) {
                    $q->where('entity_federation.status', 'active');
                },
            ])->orderBy('name')
        )->get();

        $cacheStatus = $federations->mapWithKeys(function (Federation $federation) {
            $key   = "federation_metadata:{$federation->id}";
            $ready = Cache::has($key);
            return [$federation->id => ['ready' => $ready]];
        });

        $recentFailures = EntityValidationResult::with(['entity.uiInfo'])
            ->where('passed', false)
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('entity_id');

        $search   = $request->query('q', '');
        $results  = collect();

        if ($search !== '') {
            $results = \App\Models\Entity::with('uiInfo')
                ->where(function ($q) use ($search) {
                    $q->where('entity_id', 'like', "%{$search}%")
                      ->orWhereHas('uiInfo', function ($q2) use ($search) {
                          $q2->where('field', 'display_name')
                             ->where('value', 'like', "%{$search}%");
                      });
                })
                ->limit(20)
                ->get();
        }

        $autoEnabled       = SchedulerSetting::get('metadata_auto_generate_enabled', false);
        $autoInterval      = (int) SchedulerSetting::get('metadata_auto_generate_interval', 15);
        $cacheDurationHours = (int) SchedulerSetting::get('metadata_cache_duration_hours', 6);
        $validUntilHours   = (int) SchedulerSetting::get('metadata_valid_until_hours', 48);

        return view('metadata.index', compact(
            'federations',
            'cacheStatus',
            'recentFailures',
            'search',
            'results',
            'autoEnabled',
            'autoInterval',
            'cacheDurationHours',
            'validUntilHours',
        ));
    }

    /**
     * Queue metadata generation for a federation (async background job).
     *
     * @dispatches  GenerateMetadataJob (queued, not sync — result not immediately available)
     */
    public function generate(Federation $federation): RedirectResponse
    {
        Gate::authorize('metadata.generate');

        try {
            GenerateMetadataJob::dispatchSync($federation->id);
        } catch (\Throwable $e) {
            return back()->with('error', "Metadata generation failed for \"{$federation->name}\": {$e->getMessage()}");
        }

        return back()->with('success', "Metadata generated for \"{$federation->name}\".");
    }

    public function download(Federation $federation): Response|RedirectResponse
    {
        Gate::authorize('metadata.view');

        $xml = Cache::get("federation_metadata:{$federation->id}");

        if ($xml === null) {
            return back()->with('error', 'Metadata not yet generated for this federation.');
        }

        return response($xml, 200, [
            'Content-Type'        => 'application/samlmetadata+xml',
            'Content-Disposition' => 'attachment; filename="' . $federation->slug . '-metadata.xml"',
            'Cache-Control'       => 'no-cache, must-revalidate',
            'X-Federation'        => $federation->name,
            'X-Generated-At'      => (string) $federation->metadata_generated_at,
        ]);
    }

    /**
     * Public unauthenticated endpoint: full federation aggregate XML.
     *
     * Used by eduGAIN and remote federations to fetch signed metadata.
     * Auto-generates synchronously on cache miss. Returns 404 for inactive federations.
     *
     * @authorizes  none (public endpoint)
     * @dispatches  GenerateMetadataJob::dispatchSync (on cache miss only)
     */
    public function feed(Federation $federation): Response
    {
        if ($federation->status !== 'active') {
            abort(404);
        }

        $xml = Cache::get("federation_metadata:{$federation->id}");

        if (!$xml) {
            GenerateMetadataJob::dispatchSync($federation->id);
            $xml = Cache::get("federation_metadata:{$federation->id}");
        }

        if (!$xml) {
            abort(503, 'Metadata not yet generated');
        }

        return response($xml, 200, [
            'Content-Type'        => 'application/samlmetadata+xml',
            'Content-Disposition' => 'attachment; filename="' . $federation->slug . '-feed.xml"',
            'Cache-Control'       => 'no-cache, must-revalidate',
            'X-Federation'        => $federation->name,
            'X-Generated-At'      => (string) $federation->metadata_generated_at,
        ]);
    }

    /**
     * Public unauthenticated endpoint: eduGAIN-only aggregate (entities with edugain=true).
     *
     * Auto-generates synchronously on cache miss. Returns 404 for inactive federations.
     *
     * @authorizes  none (public endpoint)
     * @dispatches  GenerateMetadataJob::dispatchSync($federationId, eduGainOnly: true) (on cache miss)
     */
    public function eduGainFeed(Federation $federation): Response
    {
        if ($federation->status !== 'active') {
            abort(404);
        }

        $xml = Cache::get("federation_edugain_metadata:{$federation->id}");

        if (!$xml) {
            GenerateMetadataJob::dispatchSync($federation->id, true);
            $xml = Cache::get("federation_edugain_metadata:{$federation->id}");
        }

        if (!$xml) {
            abort(503, 'eduGAIN metadata not yet generated');
        }

        return response($xml, 200, [
            'Content-Type'  => 'application/samlmetadata+xml',
            'Cache-Control' => 'no-cache, must-revalidate',
        ]);
    }

    /**
     * Jagger-compatible public endpoint serving the eduGAIN subset at the legacy URL pattern.
     *
     * Mirrors Jagger's /signedmetadata/federation/{name}/metadata.xml behaviour: serves only
     * entities with edugain=true (same as eduGainFeed). Looked up by jagger_fed_name.
     * Only responds when jagger_compat_enabled=true. Auto-generates synchronously on cache miss.
     *
     * @authorizes  none (public endpoint)
     * @dispatches  GenerateMetadataJob::dispatchSync(eduGainOnly: true) (on cache miss)
     */
    public function jaggerCompatFeed(string $jaggerName): Response
    {
        $federation = Federation::where('jagger_fed_name', $jaggerName)
            ->where('jagger_compat_enabled', true)
            ->where('status', 'active')
            ->firstOrFail();

        $xml = Cache::get("federation_edugain_metadata:{$federation->id}");

        if (!$xml) {
            GenerateMetadataJob::dispatchSync($federation->id, true);
            $xml = Cache::get("federation_edugain_metadata:{$federation->id}");
        }

        if (!$xml) {
            abort(503, 'Metadata not yet generated');
        }

        return response($xml, 200, [
            'Content-Type'   => 'application/samlmetadata+xml',
            'Cache-Control'  => 'no-cache, must-revalidate',
            'X-Federation'   => $federation->name,
            'X-Generated-At' => (string) $federation->metadata_generated_at,
        ]);
    }
}
