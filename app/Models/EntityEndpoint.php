<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class EntityEndpoint extends Model
{
    use HasUuids;

    public const BINDING_HTTP_POST     = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST';
    public const BINDING_HTTP_REDIRECT = 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect';
    public const BINDING_SOAP          = 'urn:oasis:names:tc:SAML:2.0:bindings:SOAP';
    public const BINDING_PAOS          = 'urn:oasis:names:tc:SAML:2.0:bindings:PAOS';

    protected $fillable = [
        'entity_id',
        'type',
        'binding',
        'location',
        'response_location',
        'index',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'index'      => 'integer',
        ];
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
