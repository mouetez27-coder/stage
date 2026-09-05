<?php

namespace App\Services\Pdf;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class InvoicePdfService
{
    public function generateAndStore(Invoice $invoice): Invoice
    {
        $invoice->loadMissing('items', 'company');

        $pdf = Pdf::loadView('pdf.invoice', ['invoice' => $invoice]);

        $filename = "pdf/{$invoice->invoice_number}.pdf";
        Storage::put($filename, $pdf->output());

        $invoice->update([
            'pdf_path' => $filename,
        ]);

        return $invoice;
    }
}