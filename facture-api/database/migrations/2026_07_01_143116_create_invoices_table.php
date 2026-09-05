<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {

            $table->id();

            // Société
            $table->foreignId('company_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Informations de la facture
            $table->string('invoice_number')->unique();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();

            $table->string('payment_method')->default('Espèces');
            $table->string('currency')->default('TND');

            // Informations du client
            $table->string('client_name');
            $table->string('client_tax_number')->nullable();

            $table->string('client_address');
            $table->string('client_city');
            $table->string('client_postal_code')->nullable();
            $table->string('client_country')->default('TN');

            $table->string('client_phone')->nullable();
            $table->string('client_email')->nullable();

            // Totaux
            $table->decimal('total_ht', 15, 3)->default(0);
            $table->decimal('total_vat', 15, 3)->default(0);
            $table->decimal('stamp_duty', 15, 3)->default(1.000);
            $table->decimal('total_ttc', 15, 3)->default(0);

            // Statut
            $table->enum('status', [
                'draft',
                'validated',
                'sent',
                'paid',
                'cancelled'
            ])->default('draft');

            // Fichiers générés
            $table->string('pdf_path')->nullable();
            $table->string('xml_path')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};