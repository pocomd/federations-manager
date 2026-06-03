<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationType extends Model
{
    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'label',
        'description',
        'mail_template_id',
        'notify_submitter',
        'notify_federation_managers',
        'notify_admins',
        'notify_entity_technical',
        'notify_entity_admin',
        'default_via_ui',
        'notify_email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'notify_submitter'           => 'boolean',
            'notify_federation_managers' => 'boolean',
            'notify_admins'              => 'boolean',
            'notify_entity_technical'    => 'boolean',
            'notify_entity_admin'        => 'boolean',
            'default_via_ui'             => 'boolean',
            'notify_email'               => 'boolean',
            'is_active'                  => 'boolean',
        ];
    }

    public function mailTemplate(): BelongsTo
    {
        return $this->belongsTo(MailTemplate::class);
    }
}
