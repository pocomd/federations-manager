<?php

declare(strict_types=1);

namespace App\Services\EduGain;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class EduGainApiService
{
    private const BASE_URL = 'https://technical.edugain.org/api.php';

    public function getEccsStatus(string $entityId): array
    {
        $cacheKey = 'eccs_status_' . md5($entityId);

        return Cache::remember($cacheKey, 3600, function () use ($entityId) {
            try {
                $response = Http::timeout(30)->get(self::BASE_URL, [
                    'action' => 'list_eccs_idps',
                    'format' => 'json',
                ]);

                if (!$response->successful()) {
                    return ['status' => 'unknown', 'message' => 'API returned HTTP ' . $response->status()];
                }

                $items = $response->json();

                if (!is_array($items)) {
                    return ['status' => 'unknown', 'message' => 'Unexpected API response format'];
                }

                foreach ($items as $item) {
                    if (($item['entityID'] ?? '') === $entityId) {
                        return [
                            'status'       => $item['status'] ?? 'unknown',
                            'entity_id'    => $entityId,
                            'displayname'  => $item['displayname'] ?? '',
                            'sso_location' => $item['Location'] ?? '',
                            'eccs_url'     => 'https://technical.edugain.org/eccs',
                        ];
                    }
                }

                return [
                    'status'   => 'not_in_eccs',
                    'message'  => 'IdP not in ECCS list — may not be in eduGAIN',
                    'eccs_url' => 'https://technical.edugain.org/eccs',
                ];

            } catch (\Exception $e) {
                return ['status' => 'unknown', 'message' => $e->getMessage()];
            }
        });
    }

    public function getEntityPresence(string $entityId): array
    {
        $cacheKey = 'edugain_entity_' . md5($entityId);

        return Cache::remember($cacheKey, 3600, function () use ($entityId) {
            try {
                $response = Http::timeout(30)->get(self::BASE_URL, [
                    'action'   => 'show_entity',
                    'entityID' => $entityId,
                    'format'   => 'json',
                ]);

                if (!$response->successful()) {
                    return [
                        'present'     => false,
                        'message'     => 'API returned HTTP ' . $response->status(),
                        'edugain_url' => 'https://technical.edugain.org/entities',
                    ];
                }

                $data = $response->json();

                if (empty($data) || isset($data['error'])) {
                    return [
                        'present'     => false,
                        'message'     => 'Entity not found in eduGAIN database',
                        'edugain_url' => 'https://technical.edugain.org/entities',
                    ];
                }

                return [
                    'present'                => true,
                    'registration_authority' => $data['registrationAuthority'] ?? '',
                    'federations'            => $data['federations'] ?? [],
                    'edugain_url'            => 'https://technical.edugain.org/entities?e=' . urlencode($entityId),
                ];

            } catch (\Exception $e) {
                return [
                    'present'     => false,
                    'message'     => $e->getMessage(),
                    'edugain_url' => 'https://technical.edugain.org/entities',
                ];
            }
        });
    }

    public function getFederationStatus(string $fedCode): array
    {
        $cacheKey = 'edugain_fed_' . $fedCode;

        return Cache::remember($cacheKey, 3600, function () use ($fedCode) {
            try {
                $response = Http::timeout(30)->get(self::BASE_URL, [
                    'action' => 'show_federation',
                    'fed_id' => $fedCode,
                    'format' => 'json',
                ]);

                if (!$response->successful()) {
                    return ['error' => 'unavailable'];
                }

                return $response->json() ?? ['error' => 'unavailable'];

            } catch (\Exception $e) {
                return ['error' => 'unavailable'];
            }
        });
    }
}
