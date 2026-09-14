<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;

class InvoicePdfService
{
    public function download(Invoice $invoice): mixed
    {
        $invoice->load([
            'workspace',
            'client',
            'items',
        ]);

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
        ])->setPaper('a4');

        return $pdf->download('invoice-'.$invoice->invoice_number.'.pdf');
    }
}