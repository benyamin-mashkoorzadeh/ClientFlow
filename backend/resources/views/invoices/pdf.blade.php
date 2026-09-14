<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 32px; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h1 { color: #111827; font-size: 28px; margin: 0; }
        h2 { color: #111827; font-size: 15px; margin: 0 0 8px; }
        p { margin: 3px 0; }
        .header { border-bottom: 2px solid #1f2937; margin-bottom: 24px; padding-bottom: 16px; }
        .header-table, .details-table, .items-table, .totals-table { border-collapse: collapse; width: 100%; }
        .header-table td { vertical-align: top; }
        .right { text-align: right; }
        .muted { color: #6b7280; }
        .status { color: #991b1b; font-size: 12px; font-weight: bold; text-transform: uppercase; }
        .details { margin-bottom: 24px; }
        .details-table td { padding-right: 24px; vertical-align: top; width: 50%; }
        .items-table { margin-top: 8px; }
        .items-table th { background: #f3f4f6; border-bottom: 1px solid #d1d5db; font-weight: bold; padding: 8px 6px; text-align: left; }
        .items-table td { border-bottom: 1px solid #e5e7eb; padding: 8px 6px; }
        .number { text-align: right !important; }
        .totals { margin-top: 18px; }
        .totals-table { width: 42%; margin-left: auto; }
        .totals-table td { padding: 4px 0; }
        .total-row { border-top: 2px solid #1f2937; font-size: 14px; font-weight: bold; }
        .notes { border-top: 1px solid #e5e7eb; margin-top: 28px; padding-top: 12px; }
    </style>
</head>
<body>
    @php
        $money = static fn (int $cents): string => $invoice->currency_code.' '.number_format($cents / 100, 2, '.', ',');
    @endphp

    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    <h1>INVOICE</h1>
                    <p class="muted">{{ $invoice->workspace->name }}</p>
                </td>
                <td class="right">
                    <h2>{{ $invoice->invoice_number }}</h2>
                    <p>Issue date: {{ $invoice->issue_date->toDateString() }}</p>
                    <p>Due date: {{ $invoice->due_date->toDateString() }}</p>
                    <p class="status">{{ $invoice->status }}</p>
                    @if ($invoice->is_overdue)
                        <p class="status">Overdue</p>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="details">
        <table class="details-table">
            <tr>
                <td>
                    <h2>Bill To</h2>
                    <p>{{ $invoice->client->name }}</p>
                    @if ($invoice->client->company)<p>{{ $invoice->client->company }}</p>@endif
                    @if ($invoice->client->email)<p>{{ $invoice->client->email }}</p>@endif
                    @if ($invoice->client->phone)<p>{{ $invoice->client->phone }}</p>@endif
                    @if ($invoice->client->address)<p>{{ $invoice->client->address }}</p>@endif
                </td>
                <td>
                    <h2>Currency</h2>
                    <p>{{ $invoice->currency_code }}</p>
                </td>
            </tr>
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="number">Qty</th>
                <th class="number">Unit price</th>
                <th class="number">Discount</th>
                <th class="number">Tax</th>
                <th class="number">Line total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="number">{{ $item->quantity }}</td>
                    <td class="number">{{ $money($item->unit_price_cents) }}</td>
                    <td class="number">{{ number_format((float) $item->discount_rate, 2) }}%</td>
                    <td class="number">{{ number_format((float) $item->tax_rate, 2) }}%</td>
                    <td class="number">{{ $money($item->line_total_cents) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table class="totals-table">
            <tr><td>Subtotal</td><td class="right">{{ $money($invoice->subtotal_cents) }}</td></tr>
            <tr><td>Discount</td><td class="right">-{{ $money($invoice->discount_cents) }}</td></tr>
            <tr><td>Tax</td><td class="right">{{ $money($invoice->tax_cents) }}</td></tr>
            <tr class="total-row"><td>Total</td><td class="right">{{ $money($invoice->total_cents) }}</td></tr>
        </table>
    </div>

    @if ($invoice->notes)
        <div class="notes">
            <h2>Notes</h2>
            <p>{{ $invoice->notes }}</p>
        </div>
    @endif
</body>
</html>