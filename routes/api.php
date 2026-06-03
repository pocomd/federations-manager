<?php

declare(strict_types=1);

use App\Http\Controllers\DiscoveryController;
use App\Http\Controllers\EntityMetadataController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Federation Manager
|--------------------------------------------------------------------------
|
| MDQ (Metadata Query Protocol) — draft-young-md-query
|   Entity identifiers are SHA1-hashed and URL-encoded.
|   Returns a signed SAML2 EntityDescriptor with Content-Type:
|   application/samlmetadata+xml.
|
| Export endpoint — returns unsigned SAML2 EntityDescriptor XML for
|   consumers who only need plain metadata (no xmlsectool dependency).
|
*/

// ── MDQ endpoint (public — no auth required, entities must be active) ─────────
// Pattern: GET /api/entities/mdq/{sha1_entityid}
// The {hash} segment matches any URL-safe characters including colons.
Route::get('entities/mdq/{hash}', [EntityMetadataController::class, 'mdq'])
    ->name('api.mdq')
    ->where('hash', '[A-Za-z0-9._~:@!$&\'()*+,;=%{}-]+');

// ── Entity metadata XML export (requires metadata.view permission) ─────────────
Route::middleware('auth:sanctum')->group(function () {
    Route::get('entities/{entity}/export', [EntityMetadataController::class, 'rawXml'])
        ->name('api.entities.export');
});

// ── JEDI discovery endpoint (public, rate-limited) ────────────────────────────
Route::middleware(['throttle:60,1'])
    ->get('/discovery/entities', [DiscoveryController::class, 'entities'])
    ->name('discovery.entities');

// ── OIDC client configuration (public, rate-limited, RFC 7591 format) ─────────
Route::middleware(['throttle:60,1'])
    ->get('/entities/{entity}/oidc-configuration', [\App\Http\Controllers\Api\OidcMetadataController::class, 'show'])
    ->name('api.entities.oidc-configuration');
