<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityValidationResult extends Model
{
    use HasUuids;

    /** Immutable — no updated_at column. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'entity_id',
        'passed',
        'errors',
        'warnings',
        'checks',
        'triggered_by',
        'triggered_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'passed'   => 'boolean',
            'errors'   => 'array',
            'warnings' => 'array',
            'checks'   => 'array',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
