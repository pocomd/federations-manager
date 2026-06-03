<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RuleDefinition extends Model
{
    protected $primaryKey = 'id';
    public    $incrementing = false;
    protected $keyType      = 'string';

    protected $guarded = [];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    protected function casts(): array
    {
        return [
            'active'     => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function federationConfigs(): HasMany
    {
        return $this->hasMany(FederationRuleConfig::class, 'rule_id');
    }

    public function entityConfigs(): HasMany
    {
        return $this->hasMany(EntityRuleConfig::class, 'rule_id');
    }
}
