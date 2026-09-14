<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id', 'description', 'quantity', 'unit_price_cents',
        'discount_rate', 'tax_rate', 'line_subtotal_cents',
        'line_discount_cents', 'line_tax_cents', 'line_total_cents',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price_cents' => 'integer',
        'discount_rate' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'line_subtotal_cents' => 'integer',
        'line_discount_cents' => 'integer',
        'line_tax_cents' => 'integer',
        'line_total_cents' => 'integer',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
