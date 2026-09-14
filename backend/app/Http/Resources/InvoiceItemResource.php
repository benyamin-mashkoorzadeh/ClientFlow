<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_price_cents' => $this->unit_price_cents,
            'discount_rate' => $this->discount_rate,
            'tax_rate' => $this->tax_rate,
            'line_subtotal_cents' => $this->line_subtotal_cents,
            'line_discount_cents' => $this->line_discount_cents,
            'line_tax_cents' => $this->line_tax_cents,
            'line_total_cents' => $this->line_total_cents,
        ];
    }
}
