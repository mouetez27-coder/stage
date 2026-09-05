<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice)
    {
    }

    public function build()
    {
        $mail = $this->subject("Facture n° {$this->invoice->invoice_number}")
            ->view('emails.invoice');

        if ($this->invoice->xml_path && Storage::exists($this->invoice->xml_path)) {
            $mail->attach(Storage::path($this->invoice->xml_path), [
                'as' => "{$this->invoice->invoice_number}.xml",
                'mime' => 'application/xml',
            ]);
        }

        if ($this->invoice->pdf_path && Storage::exists($this->invoice->pdf_path)) {
            $mail->attach(Storage::path($this->invoice->pdf_path), [
                'as' => "{$this->invoice->invoice_number}.pdf",
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}