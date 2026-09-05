<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    /**
     * Détermine si l'utilisateur est autorisé.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Règles de validation.
     */
    public function rules(): array
    {
        return [

            // =========================
            // Informations de la facture
            // =========================
            'invoice_number' => 'required|string|max:50|unique:invoices,invoice_number',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:invoice_date',

            'payment_method' => 'required|string|max:100',
            'currency' => 'required|string|size:3',

            // =========================
            // Client
            // =========================
            'client_name' => 'required|string|max:255',
            'client_tax_number' => 'nullable|string|max:100',

            'client_address' => 'required|string|max:255',
            'client_city' => 'required|string|max:100',
            'client_postal_code' => 'required|string|max:17', // max 17 selon spec TEIF v1.9.0            
            'client_country' => 'required|string|max:2',

            'client_phone' => 'nullable|string|max:30',
            'client_email' => 'nullable|email',

            // =========================
            // Lignes
            // =========================
            'items' => 'required|array|min:1',

            'items.*.code' => 'nullable|string|max:50',

            'items.*.designation' => 'required|string|max:255',

            'items.*.quantity' => 'required|numeric|min:0.001',

            'items.*.unit' => 'required|string|max:20',

            'items.*.unit_price' => 'required|numeric|min:0',

            'items.*.vat_rate' => 'required|numeric|min:0|max:100',

            'items.*.discount' => 'nullable|numeric|min:0',
        ];
    }

    /**
     * Messages personnalisés.
     */
    public function messages(): array
    {
        return [

            'invoice_number.required' => 'Le numéro de facture est obligatoire.',

            'client_name.required' => 'Le nom du client est obligatoire.',

            'items.required' => 'Vous devez ajouter au moins une ligne.',

            'items.*.designation.required' => 'La désignation est obligatoire.',

            'items.*.quantity.required' => 'La quantité est obligatoire.',

            'items.*.unit_price.required' => 'Le prix unitaire est obligatoire.',

            'items.*.vat_rate.required' => 'Le taux de TVA est obligatoire.',
        ];
    }
}