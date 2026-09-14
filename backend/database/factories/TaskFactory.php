<?php

namespace Database\Factories;

use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
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
            'project_id' => fn (array $attributes) => \App\Models\Project::factory()->create([
                'workspace_id' => $attributes['workspace_id'],
            ])->id,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'deadline' => $this->faker->date(),
            'priority' => 'medium',
            'status' => 'todo',
        ];
    }
}
