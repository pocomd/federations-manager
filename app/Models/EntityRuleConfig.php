<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityRuleConfig extends Model
{
    protected $table   = 'entity_rule_config';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled'    => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(RuleDefinition::class, 'rule_id');
    }
}
