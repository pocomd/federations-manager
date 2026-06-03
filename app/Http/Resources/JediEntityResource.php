<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JediEntityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $entity = $this->resource;
        $uiInfo = $entity->uiInfo->groupBy('field');

        return [
            'entityID'              => $entity->entity_id,
            'registrationAuthority' => $entity->registration_authority,
            'displayNames'          => $uiInfo->get('display_name', collect())
                ->map(fn ($r) => ['lang' => $r->lang, 'value' => $r->value])
                ->values(),
            'descriptions'          => $uiInfo->get('description', collect())
                ->map(fn ($r) => ['lang' => $r->lang, 'value' => $r->value])
                ->values(),
            'logos'                 => $uiInfo->get('logo_url', collect())
                ->map(fn ($r) => ['url' => $r->value, 'height' => $r->logo_height, 'width' => $r->logo_width])
                ->values(),
            'informationURLs'       => $uiInfo->get('information_url', collect())
                ->map(fn ($r) => ['lang' => $r->lang, 'url' => $r->value])
                ->values(),
        ];
    }
}
