<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FederationRuleConfig extends Model
{
    protected $table   = 'federation_rule_config';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'enabled'    => 'boolean',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(RuleDefinition::class, 'rule_id');
    }
}
