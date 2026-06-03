<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityCertificate extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'not_before'   => 'immutable_datetime',
            'not_after'    => 'immutable_datetime',
            'debian_weak'  => 'boolean',
            'key_bits'     => 'integer',
            'created_at'   => 'immutable_datetime',
            'updated_at'   => 'immutable_datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    /** Certificates expiring within $days days (but not yet expired). */
    public function scopeExpiring(Builder $query, int $days): Builder
    {
        return $query->where('not_after', '<=', now()->addDays($days))
                     ->where('not_after', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('not_after', '<', now());
    }

    /** Expiring within 14 days (critical threshold). */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->expiring(14);
    }

    /** Expiring within 30 days (warning threshold). */
    public function scopeWarning(Builder $query): Builder
    {
        return $query->expiring(30);
    }
}
