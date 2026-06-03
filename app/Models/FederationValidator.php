<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FederationValidator extends Model
{
    use HasUuids;

    protected $fillable = [
        'federation_id',
        'name',
        'description',
        'url',
        'http_method',
        'metadata_arg_name',
        'optional_args',
        'args_separator',
        'timeout',
        'response_code_element',
        'response_message_element',
        'success_value',
        'warning_value',
        'error_value',
        'critical_value',
        'enabled',
        'enabled_on_registration',
        'mandatory',
    ];

    protected function casts(): array
    {
        return [
            'enabled'                 => 'boolean',
            'enabled_on_registration' => 'boolean',
            'mandatory'               => 'boolean',
        ];
    }

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeOnRegistration(Builder $query): Builder
    {
        return $query->where('enabled_on_registration', true);
    }

    public function scopeMandatory(Builder $query): Builder
    {
        return $query->where('mandatory', true);
    }
}
