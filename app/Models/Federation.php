<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Federation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected static function booted(): void
    {
        static::creating(function (self $f): void {
            $f->slug = $f->slug ?: self::uniqueSlug($f->name);
        });

        static::updating(function (self $f): void {
            if ($f->isDirty('name') && !$f->isDirty('slug')) {
                $f->slug = self::uniqueSlug($f->name, $f->id);
            }
        });

        $bustStats = fn() => Cache::forget('registry_statistics');
        static::created($bustStats);
        static::updated($bustStats);
        static::deleted($bustStats);
    }

    private static function uniqueSlug(string $name, ?string $excludeId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        for ($i = 2; self::where('slug', $slug)->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }
        return $slug;
    }

    protected function casts(): array
    {
        return [
            'created_at'            => 'immutable_datetime',
            'updated_at'            => 'immutable_datetime',
            'deleted_at'            => 'immutable_datetime',
            'metadata_generated_at'         => 'immutable_datetime',
            'metadata_edugain_generated_at' => 'immutable_datetime',
            'jagger_compat_enabled' => 'boolean',
        ];
    }

    // ── Metadata file helpers ──────────────────────────────────────────

    public function metadataPath(bool $eduGainOnly = false): string
    {
        $file = $eduGainOnly ? 'edugain.xml' : 'metadata.xml';
        return storage_path("app/metadata/{$this->slug}/{$file}");
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'entity_federation')
            ->using(EntityFederation::class)
            ->withPivot(['status', 'approved_by', 'approved_at'])
            ->withTimestamps();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function registrationPolicies(): HasMany
    {
        return $this->hasMany(FederationRegistrationPolicy::class);
    }

    public function enabledPolicies(): HasMany
    {
        return $this->hasMany(FederationRegistrationPolicy::class)->where('enabled', true);
    }

    public function requiredAttributes(): HasMany
    {
        return $this->hasMany(FederationRequiredAttribute::class);
    }

    public function validators(): HasMany
    {
        return $this->hasMany(FederationValidator::class);
    }

    public function enabledValidators(): HasMany
    {
        return $this->hasMany(FederationValidator::class)->where('enabled', true);
    }

    public function registrationValidators(): HasMany
    {
        return $this->hasMany(FederationValidator::class)->where('enabled_on_registration', true);
    }

    public function ruleConfigs(): HasMany
    {
        return $this->hasMany(FederationRuleConfig::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(FederationContact::class);
    }

    public function softHsmToken(): HasOne
    {
        return $this->hasOne(SoftHsmToken::class)->whereNull('deleted_at');
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'federation_managers', 'federation_id', 'user_id')
            ->withPivot(['assigned_by', 'assigned_at'])
			->withCasts(['assigned_at' => 'datetime']);
    }
}
