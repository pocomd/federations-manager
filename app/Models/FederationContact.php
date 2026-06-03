<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FederationContact extends Model
{
    use HasUuids;

    protected $fillable = [
        'federation_id',
        'type',
        'given_name',
        'sur_name',
        'email',
        'phone',
    ];

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }
}
