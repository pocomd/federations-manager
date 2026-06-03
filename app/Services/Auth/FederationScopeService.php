<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FederationScopeService
{
    private bool $constrained = false;
    private Collection $allowedIds;

    public function __construct()
    {
        $this->allowedIds = collect();
    }

    public function resolve(User $user): void
    {
        if ($user->hasRole('Admin')) {
            $this->constrained = false;
            return;
        }

        $this->constrained = true;
        $this->allowedIds  = $user->hasRole('Federation Manager')
            ? $user->managedFederations()->pluck('federations.id')
            : collect();
    }

    public function isConstrained(): bool
    {
        return $this->constrained;
    }

    public function ids(): Collection
    {
        return $this->allowedIds;
    }

    public function scopeQuery(Builder $query, string $column = 'id'): Builder
    {
        if (! $this->constrained) {
            return $query;
        }

        return $query->whereIn($column, $this->allowedIds);
    }

    public function scopeEntityQuery(Builder $query): Builder
    {
        if (! $this->constrained) {
            return $query;
        }

        return $query->where(function (Builder $q) {
            $q->whereHas('federations', fn (Builder $inner) =>
                $inner->whereIn('federations.id', $this->allowedIds)
            )->orWhereDoesntHave('federations');
        });
    }
}
