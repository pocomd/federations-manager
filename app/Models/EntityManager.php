<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityManager extends Model
{
    public $timestamps = false;

    protected $table = 'entity_managers';

    public $incrementing = false;

    protected $primaryKey = ['entity_id', 'user_id'];

    protected $keyType = 'string';

    protected $fillable = [
        'entity_id',
        'user_id',
        'role',
        'added_by',
        'added_at',
    ];

    protected function casts(): array
    {
        return [
            'added_at' => 'immutable_datetime',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
