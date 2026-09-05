<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InvoiceService
{
    public function store(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {

            // ===========================
            // Calcul des totaux
            // ===========================

            $totalHT = 0;
            $totalTVA = 0;

            foreach ($data['items'] as $item) {

                $discount = $item['discount'] ?? 0;

                $lineHT = ($item['quantity'] * $item['unit_price']) - $discount;

                $lineTVA = $lineHT * ($item['vat_rate'] / 100);

                $totalHT += $lineHT;
                $totalTVA += $lineTVA;
            }

            $stampDuty = 1.000;
            $totalTTC = $totalHT + $totalTVA + $stampDuty;

            // ===========================
            // Identifiants TEIF
            // ===========================

            $company = \App\Models\Company::findOrFail($data['company_id'] ?? 1);

            $senderIdentifier = $company->tax_identifier;

            $hasClientTaxNumber = !empty($data['client_tax_number']);

            $receiverIdentifierType = $hasClientTaxNumber ? 'I-01' : 'I-05';
            $receiverIdentifier = $hasClientTaxNumber
                ? $data['client_tax_number']
                : 'DIVERS';

            // ===========================
            // Création de la facture
            // ===========================

            $invoice = Invoice::create([

                'uuid' => (string) Str::uuid(),
                'company_id' => $company->id,

                'invoice_number' => $data['invoice_number'],
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,

                'payment_method' => $data['payment_method'],
                'currency' => $data['currency'],

                'client_name' => $data['client_name'],
                'client_tax_number' => $data['client_tax_number'] ?? null,
                'client_address' => $data['client_address'],
                'client_city' => $data['client_city'],
                'client_postal_code' => $data['client_postal_code'] ?? null,
                'client_country' => $data['client_country'],
                'client_phone' => $data['client_phone'] ?? null,
                'client_email' => $data['client_email'] ?? null,

                'total_ht' => $totalHT,
                'total_vat' => $totalTVA,
                'stamp_duty' => $stampDuty,
                'total_ttc' => $totalTTC,

                'status' => 'draft',

                // TEIF
                'document_type' => $data['document_type'] ?? 'I-11', // Facture standard
                'sender_identifier' => $senderIdentifier,
                'receiver_identifier' => $receiverIdentifier,
                'transmission_channel' => null,
            ]);

            // ===========================
            // Création des lignes
            // ===========================

            foreach ($data['items'] as $item) {

                $discount = $item['discount'] ?? 0;

                $lineHT = ($item['quantity'] * $item['unit_price']) - $discount;

                InvoiceItem::create([

                    'invoice_id' => $invoice->id,

                    'code' => $item['code'] ?? null,
                    'designation' => $item['designation'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'unit_price' => $item['unit_price'],
                    'vat_rate' => $item['vat_rate'],
                    'discount' => $item['discount'] ?? 0,
                    'line_total' => $lineHT,

                    'language' => $item['language'] ?? 'fr',
                    'tax_category' => $item['tax_category'] ?? 'Rate',
                    'tax_amount' => $item['tax_amount'] ?? null,
                    'allowance_category' => $item['allowance_category'] ?? 'Rate',
                    'allowance_amount' => $item['allowance_amount'] ?? 0,
                ]);
            }

            return $invoice->load('items', 'company');
        });
    }
}