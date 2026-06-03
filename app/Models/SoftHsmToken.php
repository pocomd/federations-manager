<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftHsmToken extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = ['federation_id', 'token_label', 'slot_id', 'created_by'];

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
