<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arrival_inspections', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('draft')->index();
            $table->string('iar_number')->nullable()->unique();
            $table->date('iar_date')->nullable();
            $table->string('entity_name')->nullable();
            $table->string('fund_cluster')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('contract_title')->nullable();
            $table->string('requesting_office')->nullable();
            $table->string('responsibility_center_code')->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->string('acceptance_status')->nullable();
            $table->text('acceptance_remarks')->nullable();
            $table->date('inspection_date')->nullable();
            $table->date('receipt_date')->nullable();
            $table->string('committee_member_1_name')->nullable();
            $table->string('committee_member_1_designation')->nullable();
            $table->string('committee_member_2_name')->nullable();
            $table->string('committee_member_2_designation')->nullable();
            $table->string('committee_member_3_name')->nullable();
            $table->string('committee_member_3_designation')->nullable();
            $table->string('custodian_name')->nullable();
            $table->string('custodian_designation')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'inspection_date']);
        });

        Schema::create('arrival_inspection_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('arrival_inspection_id')
                ->constrained('arrival_inspections')
                ->restrictOnDelete();
            $table->string('stock_property_number')->nullable();
            $table->text('description');
            $table->string('unit');
            $table->unsignedInteger('quantity');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arrival_inspection_items');
        Schema::dropIfExists('arrival_inspections');
    }
};
