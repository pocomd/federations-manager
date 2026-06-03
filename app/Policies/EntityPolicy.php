<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Entity;
use App\Models\EntityManager;
use App\Models\User;

class EntityPolicy
{
    public function view(User $user, Entity $entity): bool
    {
        return $user->can('entity.view');
    }

    public function update(User $user, Entity $entity): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        if (! $user->can('entity.edit')) {
            return false;
        }

        if ($user->hasRole('Federation Manager')) {
            $myIds = $user->managedFederations()->pluck('federations.id');
            return $entity->federations()->whereIn('federations.id', $myIds)->exists()
                || ! $entity->federations()->exists();
        }

        return EntityManager::where('entity_id', $entity->id)
                            ->where('user_id', $user->id)
                            ->exists();
    }

    public function delete(User $user, Entity $entity): bool
    {
        return $user->hasRole('Admin');
    }

    public static function isManager(User $user, Entity $entity): bool
    {
        return EntityManager::where('entity_id', $entity->id)
                            ->where('user_id', $user->id)
                            ->exists();
    }
}
