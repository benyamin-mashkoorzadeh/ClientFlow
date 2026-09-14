<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workspace_id' => fn () => \App\Models\Workspace::factory(),
            'client_id' => fn (array $attributes) => \App\Models\Client::factory()->create([
                'workspace_id' => $attributes['workspace_id'],
            ])->id,
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'status' => 'active',
            'start_date' => $this->faker->date(),
            'end_date' => null,
            'budget_cents' => $this->faker->numberBetween(0, 10000000),
            'currency_code' => 'USD',
        ];
    }
}
