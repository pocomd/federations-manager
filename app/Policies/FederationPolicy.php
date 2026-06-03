<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Entity;
use App\Models\Federation;
use App\Models\User;

class FederationPolicy
{
    public function view(User $user, Federation $federation): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        if ($user->hasRole('Federation Manager')) {
            return $federation->managers()->where('user_id', $user->id)->exists();
        }

        return $user->can('federation.view');
    }

    public function update(User $user, Federation $federation): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        return $user->hasRole('Federation Manager')
            && $federation->managers()->where('user_id', $user->id)->exists();
    }

    public function approveRequest(User $user, Federation $federation): bool
    {
        return $this->update($user, $federation);
    }

    public function rejectRequest(User $user, Federation $federation): bool
    {
        return $this->update($user, $federation);
    }

    public function addEntity(User $user, Federation $federation, Entity $entity): bool
    {
        return $this->update($user, $federation);
    }

    public function removeEntity(User $user, Federation $federation): bool
    {
        return $this->update($user, $federation);
    }
}
