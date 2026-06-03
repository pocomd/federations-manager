<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityArp extends Model
{
    use HasUuids;

    protected $table = 'entity_arp';

    protected $fillable = [
        'idp_entity_id',
        'sp_entity_id',
        'attribute_definition_id',
        'is_permitted',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_permitted' => 'boolean',
        ];
    }

    public function idp(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'idp_entity_id');
    }

    public function sp(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'sp_entity_id');
    }

    public function attributeDefinition(): BelongsTo
    {
        return $this->belongsTo(AttributeDefinition::class);
    }
}
