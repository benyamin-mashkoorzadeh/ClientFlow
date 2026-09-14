<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use App\Support\CurrencyCode;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class InvoiceService
{
    public function __construct(private DatabaseManager $database)
    {
    }

    public function create(User $user, array $data): Invoice
    {
        return $this->database->transaction(function () use ($user, $data) {
            $workspace = $user->activeWorkspace();
            $items = $data['items'];
            unset($data['items']);

            $data['workspace_id'] = $workspace->id;
            if (! isset($data['currency_code'])) {
                $data['currency_code'] = CurrencyCode::workspaceDefault($workspace);
            }
            $data['invoice_number'] = $this->nextInvoiceNumber(
                $workspace->id,
                Carbon::parse($data['issue_date'])->year,
            );
            $data['status'] ??= 'draft';

            $invoice = Invoice::create($data);
            $this->replaceItems($invoice, $items);

            return $invoice->fresh(['client', 'items']);
        });
    }

    public function update(User $user, Invoice $invoice, array $data): Invoice
    {
        return $this->database->transaction(function () use ($user, $invoice, $data) {
            $itemsProvided = array_key_exists('items', $data);
            $items = $data['items'] ?? null;
            unset($data['items']);

            $this->assertEditable($invoice, $data, $itemsProvided);
            $this->assertStatusTransition($invoice->status, $data['status'] ?? $invoice->status);
            unset($data['invoice_number'], $data['workspace_id']);

            $invoice->update($data);
            if ($itemsProvided) {
                $this->replaceItems($invoice, $items);
            }

            return $invoice->fresh(['client', 'items']);
        });
    }

    public function delete(Invoice $invoice): void
    {
        if ($invoice->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => 'Only draft invoices can be deleted.',
            ]);
        }

        $invoice->delete();
    }

    private function replaceItems(Invoice $invoice, array $items): void
    {
        $invoice->items()->delete();
        $totals = [
            'subtotal_cents' => 0,
            'discount_cents' => 0,
            'tax_cents' => 0,
            'total_cents' => 0,
        ];

        foreach ($items as $item) {
            $subtotal = $item['quantity'] * $item['unit_price_cents'];
            $discount = $this->percentage($subtotal, (string) ($item['discount_rate'] ?? '0'));
            $tax = $this->percentage($subtotal - $discount, (string) ($item['tax_rate'] ?? '0'));
            $lineTotal = $subtotal - $discount + $tax;

            $invoice->items()->create([
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price_cents' => $item['unit_price_cents'],
                'discount_rate' => $item['discount_rate'] ?? 0,
                'tax_rate' => $item['tax_rate'] ?? 0,
                'line_subtotal_cents' => $subtotal,
                'line_discount_cents' => $discount,
                'line_tax_cents' => $tax,
                'line_total_cents' => $lineTotal,
            ]);

            $totals['subtotal_cents'] += $subtotal;
            $totals['discount_cents'] += $discount;
            $totals['tax_cents'] += $tax;
            $totals['total_cents'] += $lineTotal;
        }

        $invoice->update($totals);
    }

    private function nextInvoiceNumber(int $workspaceId, int $year): string
    {
        $prefix = "INV-{$year}-";
        $latest = Invoice::withTrashed()
            ->where('workspace_id', $workspaceId)
            ->where('invoice_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $sequence = $latest ? ((int) substr($latest, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function percentage(int $amount, string $rate): int
    {
        [$whole, $fraction] = array_pad(explode('.', $rate, 2), 2, '0');
        $rateHundredths = ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return intdiv(($amount * $rateHundredths) + 5000, 10000);
    }

    private function assertEditable(Invoice $invoice, array $data, bool $itemsProvided): void
    {
        if ($invoice->status === 'draft') {
            return;
        }

        $materialFields = array_diff(array_keys($data), ['status']);
        if ($itemsProvided || $materialFields !== []) {
            throw ValidationException::withMessages([
                'status' => 'Only draft invoices can be materially edited.',
            ]);
        }
    }

    private function assertStatusTransition(string $current, string $next): void
    {
        if ($current === $next) {
            return;
        }

        $allowed = [
            'draft' => ['sent', 'cancelled'],
            'sent' => ['paid', 'cancelled'],
            'paid' => [],
            'cancelled' => [],
        ];

        if (! in_array($next, $allowed[$current] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot change an {$current} invoice to {$next}.",
            ]);
        }
    }
}
