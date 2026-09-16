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
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('signed_at')->nullable()->after('xml_path');
            $table->string('signed_xml_path')->nullable()->after('signed_at');
            $table->string('signer_dn')->nullable()->after('signed_xml_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'signed_at',
                'signed_xml_path',
                'signer_dn',
            ]);
        });
    }
};
