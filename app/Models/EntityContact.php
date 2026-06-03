<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityContact extends Model
{
    use HasUuids;

    protected $fillable = [
        'entity_id',
        'type',
        'given_name',
        'sur_name',
        'email',
        'phone',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
