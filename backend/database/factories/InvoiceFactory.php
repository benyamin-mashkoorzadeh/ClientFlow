<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issueDate = $this->faker->dateTimeBetween('-30 days', 'today');

        return [
            'workspace_id' => fn () => \App\Models\Workspace::factory(),
            'client_id' => fn (array $attributes) => \App\Models\Client::factory()->create([
                'workspace_id' => $attributes['workspace_id'],
            ])->id,
            'invoice_number' => 'INV-'.$issueDate->format('Y').'-'.$this->faker->unique()->numerify('####'),
            'issue_date' => $issueDate,
            'due_date' => (clone $issueDate)->modify('+14 days'),
            'status' => 'draft',
            'currency_code' => 'USD',
            'subtotal_cents' => 0,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'total_cents' => 0,
            'notes' => null,
        ];
    }
}
