<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'code',
        'designation',
        'quantity',
        'unit',
        'unit_price',
        'vat_rate',
        'discount',
        'line_total',

        // Champs TEIF
        'language',
        'tax_category',
        'tax_amount',
        'allowance_category',
        'allowance_amount',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}