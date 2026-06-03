<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    use HasUuids;

    const UPDATED_AT = null;

    protected $fillable = [
        'email',
        'token',
        'role',
        'invited_by',
        'federation_id',
        'entity_id',
        'revoked_by',
        'invitation_request_id',
        'expires_at',
        'accepted_at',
        'revoked_at',
        'reissue_comment',
        'previous_token',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'  => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime',
            'revoked_at'  => 'immutable_datetime',
            'created_at'  => 'immutable_datetime',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->whereNotNull('accepted_at');
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '<', now());
    }

    public function scopeRevoked(Builder $query): Builder
    {
        return $query->whereNotNull('revoked_at');
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function invitationRequest(): BelongsTo
    {
        return $this->belongsTo(InvitationRequest::class);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && !$this->isExpired();
    }
}
