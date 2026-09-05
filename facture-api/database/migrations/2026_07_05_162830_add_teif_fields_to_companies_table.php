<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {

            $table->string('iban', 34)->nullable();

            $table->string('bic')->nullable();

            // Type d'identifiant émetteur TEIF (toujours I-01 en v1.9.0)
            $table->string('sender_identifier_type', 4)->default('I-01');

            // Langue par défaut des documents (fr/en/ar/or)
            $table->string('language', 2)->default('fr');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {

            $table->dropColumn([
                'iban',
                'bic',
                'sender_identifier_type',
                'language',
            ]);
        });
    }
};