<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'slug' => $this->faker->unique()->slug(2),
            'description' => $this->faker->optional()->paragraph(),
            'owner_id' => \App\Models\User::factory(),
            'settings' => [
                'max_workflows' => 100,
                'max_executions_per_month' => 10000,
                'features' => ['webhooks', 'api', 'scheduling'],
            ],
            'is_active' => true,
        ];
    }
}
