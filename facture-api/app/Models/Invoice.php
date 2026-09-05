<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'uuid',
        'company_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'payment_method',
        'currency',

        'client_name',
        'client_tax_number',
        'client_address',
        'client_city',
        'client_postal_code',
        'client_country',
        'client_phone',
        'client_email',

        'total_ht',
        'total_vat',
        'stamp_duty',
        'total_ttc',

        'status',

        'pdf_path',
        'xml_path',

        // Champs TEIF
        'document_type',
        'sender_identifier',
        'receiver_identifier',
        'transmission_channel',
        'transmission_reference',
        'acknowledgment_reference',
        'sent_at',
        'acknowledged_at',
        'rejection_reason',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}