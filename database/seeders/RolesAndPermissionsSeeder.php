<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /** All permissions defined in CONTEXT.md. */
    private const PERMISSIONS = [
        // entity.*
        'entity.view',
        'entity.create',
        'entity.edit',
        'entity.delete',
        'entity.addToFederation',
        'entity.removeFromFederation',
        'entity.submitForFederation',
        'entity.requestContactInvitation',

        // federation.*
        'federation.view',
        'federation.create',
        'federation.edit',
        'federation.approveRequest',
        'federation.rejectRequest',

        // metadata.*
        'metadata.generate',
        'metadata.sign',
        'metadata.view',

        // user.*
        'user.view',
        'user.create',
        'user.edit',
        'user.delete',
        'user.invite',

        // invitation.*
        'invitation.manage',
        'invitation.view',

        // arp.*
        'arp.view',
        'arp.edit',

        // compliance.*
        'compliance.view',
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // ── Admin — full access ─────────────────────────────────────────
        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin->syncPermissions(self::PERMISSIONS);

        // ── Federation Manager ──────────────────────────────────────────
        $federationManager = Role::firstOrCreate(['name' => 'Federation Manager', 'guard_name' => 'web']);
        $federationManager->syncPermissions([
            'federation.view',
            'federation.edit',
            'federation.approveRequest',
            'federation.rejectRequest',
            'entity.view',
            'entity.edit',
            'entity.addToFederation',
            'entity.removeFromFederation',
            'metadata.generate',
            'metadata.sign',
            'metadata.view',
            'compliance.view',
            'user.invite',
            'invitation.manage',
        ]);

        // ── Entity Manager ──────────────────────────────────────────────
        $entityManager = Role::firstOrCreate(['name' => 'Entity Manager', 'guard_name' => 'web']);
        $entityManager->syncPermissions([
            'entity.view',
            'entity.create',
            'entity.edit',
            'entity.submitForFederation',
            'entity.requestContactInvitation',
            'metadata.view',
            'invitation.view',
        ]);

        // ── Guest — read-only ───────────────────────────────────────────
        $guest = Role::firstOrCreate(['name' => 'Guest', 'guard_name' => 'web']);
        $guest->syncPermissions([
            'metadata.view',
        ]);

        // ── Clean up legacy Operator role ───────────────────────────────
        Role::where('name', 'Operator')->first()?->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
