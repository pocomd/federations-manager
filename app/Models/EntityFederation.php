<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class EntityFederation extends Pivot
{
    protected $table = 'entity_federation';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'approved_at' => 'immutable_datetime',
            'created_at'  => 'immutable_datetime',
            'updated_at'  => 'immutable_datetime',
        ];
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Returns true if the entity already has an active membership in any federation
     * other than $exceptFederationId. Used to enforce the one-active-membership rule.
     */
    public static function hasActiveMembership(string $entityId, ?string $exceptFederationId = null): bool
    {
        return static::where('entity_id', $entityId)
            ->where('status', 'active')
            ->when($exceptFederationId, fn ($q) => $q->where('federation_id', '!=', $exceptFederationId))
            ->exists();
    }
}
