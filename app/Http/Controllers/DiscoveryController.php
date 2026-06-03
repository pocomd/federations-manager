<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\JediEntityResource;
use App\Models\Entity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DiscoveryController extends Controller
{
    public function webFinger(Request $request): JsonResponse
    {
        $resourceId = $request->query('resource');

        if (! $resourceId) {
            return response()->json(['error' => 'resource required'], 400);
        }

        $entity = Entity::where('entity_id', $resourceId)
            ->where('status', 'active')
            ->first();

        if (! $entity) {
            return response()->json([], 404);
        }

        $federation = $entity->federations()
            ->wherePivot('status', 'active')
            ->first();

        $href = $federation
            ? route('metadata.feed', $federation)
            : null;

        return response()->json([
            'subject' => $resourceId,
            'links'   => $href ? [[
                'rel'  => 'urn:oasis:names:tc:SAML:2.0:metadata',
                'href' => $href,
            ]] : [],
        ]);
    }

    public function entities(Request $request): JsonResponse
    {
        $params   = $request->only(['type', 'federation', 'q', 'per_page']);
        $cacheKey = 'discovery_jedi_' . md5(serialize($params));

        $data = Cache::remember($cacheKey, 900, function () use ($params) {
            $q = Entity::with(['uiInfo'])->where('status', 'active');

            if (! empty($params['type']) && in_array($params['type'], ['idp', 'sp'], true)) {
                $q->where('type', $params['type']);
            }

            if (! empty($params['federation'])) {
                $q->whereHas('federations', fn ($fq) => $fq
                    ->where('uri', $params['federation'])
                    ->wherePivot('status', 'active'));
            }

            if (! empty($params['q'])) {
                $q->whereHas('uiInfo', fn ($uq) => $uq
                    ->where('field', 'display_name')
                    ->where('value', 'LIKE', "%{$params['q']}%"));
            }

            $perPage = min((int) ($params['per_page'] ?? 100), 100);

            return JediEntityResource::collection($q->paginate($perPage))
                ->response()
                ->getData(true);
        });

        return response()->json($data);
    }
}
