<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Federation;
use Illuminate\Database\Seeder;

class FederationSeeder extends Seeder
{
    public function run(): void
    {
        $registrationAuthority = config('federation.registration_authority')
            ?: 'https://federation.example.com';

        Federation::firstOrCreate(
            ['uri' => $registrationAuthority],
            [
                'name'         => 'Default Federation',
                'description'  => 'The primary federation managed by this registry.',
                'status'       => 'active',
                'metadata_url' => null,
            ]
        );
    }
}
