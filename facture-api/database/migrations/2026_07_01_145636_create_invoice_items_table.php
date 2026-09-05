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
        Schema::create('invoice_items', function (Blueprint $table) {

            $table->id();

            $table->foreignId('invoice_id')
                  ->constrained()
                  ->cascadeOnDelete();

            // Code interne (optionnel)
            $table->string('code')->nullable();

            // Désignation de l'article
            $table->string('designation');

            // Quantité
            $table->decimal('quantity', 10, 3);

            // Unité (UNIT, KGM, LTR...)
            $table->string('unit')->default('UNIT');

            // Prix unitaire HT
            $table->decimal('unit_price', 15, 3);

            // Taux TVA (%)
            $table->decimal('vat_rate', 5, 2);

            // Remise
            $table->decimal('discount', 15, 3)->default(0);

            // Total HT de la ligne
            $table->decimal('line_total', 15, 3);

            

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};