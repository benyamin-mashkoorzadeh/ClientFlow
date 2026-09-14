<?php

namespace Database\Factories;

use App\Models\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id' => fn () => \App\Models\Invoice::factory(),
            'description' => $this->faker->sentence(),
            'quantity' => 1,
            'unit_price_cents' => $this->faker->numberBetween(100, 100000),
            'discount_rate' => 0,
            'tax_rate' => 0,
            'line_subtotal_cents' => 0,
            'line_discount_cents' => 0,
            'line_tax_cents' => 0,
            'line_total_cents' => 0,
        ];
    }
}
