<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Entity extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'nameid_formats'                => 'array',
            'requested_attributes'          => 'array',
            'registration_policies'         => 'array',
            'edugain'                       => 'boolean',
            'sp_want_authn_requests_signed' => 'boolean',
            'sp_want_assertions_signed'     => 'boolean',
            'source'                        => 'string',
            'created_at'                    => 'immutable_datetime',
            'updated_at'                    => 'immutable_datetime',
            'deleted_at'                    => 'immutable_datetime',
        ];
    }

    public function getNameidFormatsAttribute($value): array
    {
        if (is_array($value)) return $value;
        if (is_string($value) && $value !== '') return json_decode($value, true) ?? [];
        return [];
    }

    public function getRequestedAttributesAttribute($value): array
    {
        if (is_array($value)) return $value;
        if (is_string($value) && $value !== '') return json_decode($value, true) ?? [];
        return [];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Entity $entity): void {
            $entity->sha1_entity_id = sha1($entity->entity_id);
        });
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function certificates(): HasMany
    {
        return $this->hasMany(EntityCertificate::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(EntityContact::class)->orderBy('created_at');
    }

    public function uiInfo(): HasMany
    {
        return $this->hasMany(EntityUiInfo::class);
    }

    public function endpoints(): HasMany
    {
        return $this->hasMany(EntityEndpoint::class);
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(EntityAttribute::class);
    }

    public function validationResults(): HasMany
    {
        return $this->hasMany(EntityValidationResult::class)->orderByDesc('created_at');
    }

    public function currentValidation(): HasOne
    {
        return $this->hasOne(EntityValidationResult::class)->latestOfMany('created_at');
    }

    public function oidcConfig(): HasOne
    {
        return $this->hasOne(EntityOidcConfig::class);
    }

    public function federations(): BelongsToMany
    {
        return $this->belongsToMany(Federation::class, 'entity_federation')
            ->using(EntityFederation::class)
            ->withPivot(['status', 'approved_by', 'approved_at'])
            ->withTimestamps();
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function entityRequestedAttributes(): HasMany
    {
        return $this->hasMany(EntityRequestedAttribute::class);
    }

    public function arpReleases(): HasMany
    {
        return $this->hasMany(EntityArp::class, 'idp_entity_id');
    }

    public function arpRestrictions(): HasMany
    {
        return $this->hasMany(EntityArp::class, 'sp_entity_id');
    }

    public function ruleConfigs(): HasMany
    {
        return $this->hasMany(EntityRuleConfig::class);
    }

    public function entityManagers(): HasMany
    {
        return $this->hasMany(EntityManager::class);
    }

    public function owners(): HasMany
    {
        return $this->hasMany(EntityManager::class)->where('role', 'owner');
    }

    public function federationInvitations(): HasMany
    {
        return $this->hasMany(FederationEntityInvitation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    // ── Helper methods ─────────────────────────────────────────────────

    public function getDisplayName(string $lang = 'en'): ?string
    {
        return $this->uiInfo
            ->where('field', 'display_name')
            ->where('lang', $lang)
            ->first()?->value;
    }

    public function getEndpoints(string $type): Collection
    {
        return $this->endpoints
            ->where('type', $type)
            ->sortBy('index')
            ->values();
    }

    public function hasEntityCategory(string $uri): bool
    {
        return $this->attributes
            ->where('attribute_name', EntityAttribute::ATTR_ENTITY_CATEGORY)
            ->where('attribute_value', $uri)
            ->isNotEmpty();
    }

    public function hasAssuranceProfile(string $uri): bool
    {
        return $this->attributes
            ->where('attribute_name', EntityAttribute::ATTR_ASSURANCE_PROFILE)
            ->where('attribute_value', $uri)
            ->isNotEmpty();
    }

    public function hasSecurityContact(): bool
    {
        return $this->contacts
            ->where('type', 'security')
            ->isNotEmpty();
    }

    public function currentValidationPassed(): bool
    {
        return $this->currentValidation?->passed ?? false;
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeIdp(Builder $query): Builder
    {
        return $query->where('type', 'idp');
    }

    public function scopeSp(Builder $query): Builder
    {
        return $query->where('type', 'sp');
    }

    public function scopeEdugain(Builder $query): Builder
    {
        return $query->where('edugain', true);
    }

    public function scopeExpiredCerts(Builder $query): Builder
    {
        return $query->whereHas('certificates', function (Builder $q): void {
            $q->expired();
        });
    }
}
