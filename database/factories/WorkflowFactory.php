<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workflow>
 */
class WorkflowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(3),
            'slug' => $this->faker->unique()->slug(3),
            'description' => $this->faker->optional()->paragraph(),
            'organization_id' => \App\Models\Organization::factory(),
            'team_id' => null,
            'user_id' => \App\Models\User::factory(),
            'workflow_data' => [
                'nodes' => [],
                'connections' => [],
                'settings' => [],
            ],
            'settings' => [],
            'status' => $this->faker->randomElement(['draft', 'published', 'archived']),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'is_template' => false,
            'version' => 1,
            'tags' => $this->faker->optional()->words(3),
            'last_executed_at' => null,
            'execution_count' => 0,
        ];
    }
}
