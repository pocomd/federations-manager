<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Entity;
use App\Models\SystemPreference;
use App\Services\EduGain\EduGainApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class EduGainController extends Controller
{
    public function entityStatus(Entity $entity): JsonResponse
    {
        Gate::authorize('entity.view');

        if (!SystemPreference::get('edugain_checks_enabled', false)) {
            return response()->json(['enabled' => false]);
        }

        $result = ['enabled' => true, 'entity_id' => $entity->entity_id];

        if ($entity->type === 'idp' && SystemPreference::get('eccs_check_enabled', true)) {
            $result['eccs'] = app(EduGainApiService::class)->getEccsStatus($entity->entity_id);
        }

        if (SystemPreference::get('edugain_entity_check_enabled', true)) {
            $result['presence'] = app(EduGainApiService::class)->getEntityPresence($entity->entity_id);
        }

        return response()->json($result);
    }

    public function federationStatus(): JsonResponse
    {
        Gate::authorize('entity.view');

        if (!SystemPreference::get('edugain_checks_enabled', false)) {
            return response()->json(['enabled' => false]);
        }

        $code = SystemPreference::get('edugain_federation_code', '');

        if (!$code) {
            return response()->json(['error' => 'Federation code not configured']);
        }

        return response()->json(
            app(EduGainApiService::class)->getFederationStatus($code)
        );
    }
}
