<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationPreference extends Model
{
    public $timestamps = false;

    protected $table = 'user_notification_preferences';

    public $incrementing = false;

    protected $primaryKey = ['user_id', 'notification_type'];

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'notification_type',
        'via_ui',
        'via_email',
    ];

    protected function casts(): array
    {
        return [
            'via_ui'    => 'boolean',
            'via_email' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notificationType(): BelongsTo
    {
        return $this->belongsTo(NotificationType::class, 'notification_type');
    }
}
