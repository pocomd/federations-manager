<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AttributeDefinition extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'schema',
        'full_name',
        'saml2_oid',
        'saml1_urn',
        'description',
        'is_required',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active'   => 'boolean',
        ];
    }

    public function requestedByEntities(): BelongsToMany
    {
        return $this->belongsToMany(
            Entity::class,
            'entity_requested_attributes',
            'attribute_definition_id',
            'entity_id'
        )->withPivot(['is_required', 'reason'])->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForSchema(Builder $query, string $schema): Builder
    {
        return $query->where('schema', $schema);
    }
}
