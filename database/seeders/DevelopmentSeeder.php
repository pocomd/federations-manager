<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Entity;
use App\Models\EntityAttribute;
use App\Models\EntityCertificate;
use App\Models\EntityContact;
use App\Models\EntityEndpoint;
use App\Models\EntityUiInfo;
use App\Models\EntityValidationResult;
use App\Models\Federation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure roles exist (safe to re-run)
        $this->call(RolesAndPermissionsSeeder::class);

        // ── Clear previous dev data (idempotent) ────────────────────────
        // Disable FK checks so cascade order doesn't matter (driver-agnostic)
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        EntityValidationResult::query()->delete();
        EntityAttribute::query()->delete();
        EntityEndpoint::query()->delete();
        EntityContact::query()->delete();
        EntityUiInfo::query()->delete();
        EntityCertificate::query()->delete();
        DB::table('entity_federation')->delete();
        Entity::withTrashed()->forceDelete();
        User::where('email', 'admin@example.com')->orWhere('email', 'operator@example.com')->forceDelete();

        if ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $federation = Federation::first();

        // ── Users ───────────────────────────────────────────────────────

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name'     => 'Admin User',
                'password' => Hash::make('password123'),
                'role'     => 'Admin',
            ]
        );
        $admin->syncRoles('Admin');

        $operator = User::firstOrCreate(
            ['email' => 'operator@example.com'],
            [
                'name'     => 'Federation Manager User',
                'password' => Hash::make('password123'),
                'role'     => 'Federation Manager',
            ]
        );
        $operator->syncRoles('Federation Manager');

        // ── 5 IdP entities with signing certificates ────────────────────

        Entity::factory()
            ->idp()
            ->count(5)
            ->create()
            ->each(function (Entity $entity) use ($federation): void {
                EntityCertificate::factory()->create(['entity_id' => $entity->id]);

                if ($federation) {
                    $entity->federations()->attach($federation->id, ['status' => 'active']);
                }
            });

        // ── 5 SP entities with signing certificates ─────────────────────

        Entity::factory()
            ->sp()
            ->count(5)
            ->create()
            ->each(function (Entity $entity) use ($federation): void {
                EntityCertificate::factory()->create(['entity_id' => $entity->id]);

                if ($federation) {
                    $entity->federations()->attach($federation->id, ['status' => 'active']);
                }
            });

        // ── 2 entities with certificates expiring in 10 days ────────────
        // (mix of IdP and SP to exercise both code paths)

        $expiringIdp = Entity::factory()->idp()->create();
        EntityCertificate::factory()->expiringSoon(10)->create(['entity_id' => $expiringIdp->id]);
        if ($federation) {
            $expiringIdp->federations()->attach($federation->id, ['status' => 'active']);
        }

        $expiringSp = Entity::factory()->sp()->create();
        EntityCertificate::factory()->expiringSoon(10)->create(['entity_id' => $expiringSp->id]);
        if ($federation) {
            $expiringSp->federations()->attach($federation->id, ['status' => 'active']);
        }
    }
}
