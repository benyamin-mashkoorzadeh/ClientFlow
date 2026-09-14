<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id', 'client_id', 'invoice_number', 'issue_date', 'due_date',
        'status', 'currency_code', 'subtotal_cents', 'discount_cents',
        'tax_cents', 'total_cents', 'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'subtotal_cents' => 'integer',
        'discount_cents' => 'integer',
        'tax_cents' => 'integer',
        'total_cents' => 'integer',
        'deleted_at' => 'datetime',
    ];

    protected $appends = ['is_overdue'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->due_date?->isBefore(today())
            && ! in_array($this->status, ['paid', 'cancelled'], true);
    }
}
