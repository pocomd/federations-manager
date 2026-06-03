<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Entity;
use App\Services\Webhook\WebhookService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;

/**
 * EntityObserver
 *
 * Records created, updated and deleted events on the Entity model into audit_logs.
 * Also invalidates federation metadata caches when entity state changes.
 *
 * Registered in AppServiceProvider::boot().
 */
class EntityObserver
{
    public function created(Entity $entity): void
    {
        AuditLog::create([
            'user_id'    => Auth::id() ?? null,
            'entity_id'  => $entity->id,
            'action'     => 'created',
            'old_values' => null,
            'new_values' => $entity->getAttributes(),
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent(),
        ]);

        app(WebhookService::class)->dispatch('entity.created', [
            'entity_id' => $entity->entity_id,
            'type'      => $entity->type,
            'status'    => $entity->status,
        ]);
    }

    public function updated(Entity $entity): void
    {
        AuditLog::create([
            'user_id'    => Auth::id() ?? null,
            'entity_id'  => $entity->id,
            'action'     => 'updated',
            'old_values' => $entity->getOriginal(),
            'new_values' => $entity->getChanges(),
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent(),
        ]);

        $this->invalidateFederationCache($entity);
    }

    public function deleted(Entity $entity): void
    {
        AuditLog::create([
            'user_id'    => Auth::id() ?? null,
            'entity_id'  => $entity->isForceDeleting() ? null : $entity->id,
            'action'     => 'deleted',
            'old_values' => $entity->getOriginal(),
            'new_values' => null,
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent(),
        ]);

        $this->invalidateFederationCache($entity);
    }

    public function restored(Entity $entity): void
    {
        AuditLog::create([
            'user_id'    => Auth::id() ?? null,
            'entity_id'  => $entity->id,
            'action'     => 'restored',
            'old_values' => null,
            'new_values' => $entity->getAttributes(),
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent(),
        ]);

        $this->invalidateFederationCache($entity);
    }

    private function invalidateFederationCache(Entity $entity): void
    {
        $entity->loadMissing('federations');

        foreach ($entity->federations as $federation) {
            Cache::forget("federation_metadata:{$federation->id}");
            Cache::forget("federation_edugain_metadata:{$federation->id}");
        }

        // Invalidate JEDI discovery cache marker — forces next discovery
        // request to bypass 15-min TTL and rebuild from DB.
        Cache::forget('discovery_jedi_flush_marker');
    }
}
