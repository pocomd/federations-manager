<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailTemplate extends Model
{
    use HasUuids;

    protected $fillable = [
        'federation_id',
        'name',
        'group',
        'subject',
        'body',
        'lang',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public const GROUPS = [
        'entity_registration'     => 'Entity registration request',
        'entity_suspended'        => 'Entity suspended',
        'entity_reactivated'      => 'Entity reactivated',
        'federation_registration' => 'Federation registration request',
        'federation_deactivated'  => 'Federation deactivated',
        'certificate_expiry'      => 'Certificate expiry warning',
        'compliance_failure'      => 'Compliance check failure',
        'general'                 => 'General announcement',
    ];

    public const PLACEHOLDERS = [
        '[[federation_name]]'        => 'Federation name',
        '[[entity_name]]'            => 'Entity display name',
        '[[entity_id]]'              => 'Entity SAML entityID',
        '[[entity_type]]'            => 'IdP or SP',
        '[[contact_name]]'           => 'Contact full name',
        '[[contact_email]]'          => 'Contact email address',
        '[[contact_type]]'           => 'Contact type (technical/support/security)',
        '[[registration_authority]]' => 'Federation registration authority URI',
        '[[cert_expiry_date]]'       => 'Certificate expiry date',
        '[[cert_subject]]'           => 'Certificate subject',
        '[[validation_errors]]'      => 'Compliance check failures (errors + warnings)',
        '[[app_name]]'               => 'Application name',
        '[[app_url]]'                => 'Application URL',
        '[[mail_signature]]'         => 'Mail signature from System Preferences',
    ];

    public function federation(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Federation::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSystem(Builder $query): Builder
    {
        return $query->whereNull('federation_id');
    }

    public function scopeForGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }
}
