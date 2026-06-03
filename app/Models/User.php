<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasUuids, HasRoles, Notifiable;

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'immutable_datetime',
            'password'          => 'hashed',
            'last_login_at'     => 'immutable_datetime',
            'created_at'        => 'immutable_datetime',
            'updated_at'        => 'immutable_datetime',
        ];
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    // ── Relationships ──────────────────────────────────────────────────

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by');
    }

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }

    public function managedFederations(): BelongsToMany
    {
        return $this->belongsToMany(Federation::class, 'federation_managers', 'user_id', 'federation_id');
    }

    public function ownedEntities(): HasManyThrough
    {
        return $this->hasManyThrough(
            Entity::class,
            EntityManager::class,
            'user_id',
            'id',
            'id',
            'entity_id',
        )->where('entity_managers.role', 'owner');
    }

    public function managedEntities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'entity_managers', 'user_id', 'entity_id')
            ->withPivot(['role', 'added_by', 'added_at']);
    }
}
