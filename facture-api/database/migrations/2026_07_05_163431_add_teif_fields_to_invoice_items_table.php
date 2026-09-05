<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {

            $table->string('language',2)->default('fr');

            $table->string('tax_category')->default('Rate');

            $table->decimal('tax_amount',15,3)->nullable();

            $table->string('allowance_category')->default('Amount');

            $table->decimal('allowance_amount',15,3)->default(0);

        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {

            $table->dropColumn([
                'language',
                'tax_category',
                'tax_amount',
                'allowance_category',
                'allowance_amount',
            ]);

        });
    }
};