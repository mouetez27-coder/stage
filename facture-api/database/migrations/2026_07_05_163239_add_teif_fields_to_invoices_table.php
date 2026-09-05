<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $table->uuid('uuid')->nullable()->after('id');

            $table->string('document_type',10)->default('I-11');

            $table->string('sender_identifier',30)->nullable();

            $table->string('receiver_identifier',30)->nullable();

            $table->string('transmission_channel')
                ->nullable();

            $table->string('transmission_reference')
                ->nullable();

            $table->string('acknowledgment_reference')
                ->nullable();

            $table->timestamp('sent_at')
                ->nullable();

            $table->timestamp('acknowledged_at')
                ->nullable();

            $table->text('rejection_reason')
                ->nullable();

        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $table->dropColumn([
                'uuid',
                'document_type',
                'sender_identifier',
                'receiver_identifier',
                'transmission_channel',
                'transmission_reference',
                'acknowledgment_reference',
                'sent_at',
                'acknowledged_at',
                'rejection_reason'
            ]);

        });
    }
};