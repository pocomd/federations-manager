<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationArchive extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $table = 'notification_archive';

    protected $fillable = [
        'id',
        'user_id',
        'type',
        'title',
        'body',
        'subject_type',
        'subject_id',
        'action_url',
        'read_at',
        'created_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'subject_id'  => 'string',
            'read_at'     => 'immutable_datetime',
            'created_at'  => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notificationType(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class, 'type');
    }
}
