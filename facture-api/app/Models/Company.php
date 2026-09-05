<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    protected $fillable = [
        'name',
        'tax_identifier',
        'tax_registration_number',
        'trade_register',
        'commercial_register',
        'vat_number',
        'address',
        'city',
        'postal_code',
        'country',
        'phone',
        'email',
        'website',
        'iban',
        'bic',

        // Champs TEIF
        'sender_identifier_type',
        'language',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}