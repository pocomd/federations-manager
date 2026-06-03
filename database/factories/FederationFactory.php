<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Federation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Federation>
 */
class FederationFactory extends Factory
{
    protected $model = Federation::class;

    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'name'         => fake()->company() . ' Federation',
            'description'  => fake()->sentence(),
            'uri'          => "https://federation.{$domain}",
            'status'       => 'active',
            'metadata_url' => null,
        ];
    }

    /** Inactive federation state. */
    public function inactive(): static
    {
        return $this->state(['status' => 'inactive']);
    }
}
