<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use Illuminate\Http\JsonResponse;

class OidcMetadataController extends Controller
{
    public function show(Entity $entity): JsonResponse
    {
        if ($entity->type !== 'oidc') {
            abort(404);
        }

        $cfg = $entity->oidcConfig;

        if (! $cfg) {
            return response()->json(['error' => 'No OIDC configuration found'], 404);
        }

        return response()->json([
            'client_id'                  => $cfg->client_id,
            'redirect_uris'              => $cfg->redirect_uris ?? [],
            'grant_types'                => $cfg->grant_types ?? [],
            'response_types'             => $cfg->response_types ?? [],
            'scope'                      => implode(' ', $cfg->scopes ?? []),
            'application_type'           => $cfg->application_type,
            'token_endpoint_auth_method' => $cfg->token_endpoint_auth_method,
            'logo_uri'                   => $cfg->logo_uri,
            'policy_uri'                 => $cfg->policy_uri,
            'tos_uri'                    => $cfg->tos_uri,
        ]);
    }
}
