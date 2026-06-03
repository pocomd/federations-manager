<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityOidcConfig extends Model
{
    use HasUuids;

    protected $table = 'entity_oidc_config';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'redirect_uris'  => 'array',
            'grant_types'    => 'array',
            'response_types' => 'array',
            'scopes'         => 'array',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
