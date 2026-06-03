<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SchedulerSettingsSeeder::class,
            SystemPreferencesSeeder::class,
            AttributeDefinitionsSeeder::class,
            MailTemplatesSeeder::class,
            NotificationTypesSeeder::class,
            FederationSeeder::class,
        ]);
    }
}
