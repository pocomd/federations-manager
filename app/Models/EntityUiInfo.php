<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityUiInfo extends Model
{
    use HasUuids;

    protected $table = 'entity_ui_info';

    protected $fillable = [
        'entity_id',
        'field',
        'lang',
        'value',
        'logo_height',
        'logo_width',
    ];

    protected function casts(): array
    {
        return [
            'logo_height' => 'integer',
            'logo_width'  => 'integer',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
