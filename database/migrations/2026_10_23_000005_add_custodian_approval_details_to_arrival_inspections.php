<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('arrival_inspections', function (Blueprint $table) {
            $table->string('custodian_signature_name')->nullable();
            $table->text('custodian_return_reason')->nullable();
            $table->timestamp('custodian_returned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('arrival_inspections', function (Blueprint $table) {
            $table->dropColumn([
                'custodian_signature_name',
                'custodian_return_reason',
                'custodian_returned_at',
            ]);
        });
    }
};
