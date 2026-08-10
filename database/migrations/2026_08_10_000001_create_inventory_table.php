<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table) {
            $table->id('item_id');
            $table->foreignId('category_id')->constrained('categories', 'category_id')->restrictOnDelete();
            $table->string('unit');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('item_name');
            $table->text('description')->nullable();
            $table->unsignedInteger('quantity');
            $table->string('ics_no')->nullable();
            $table->string('serial_number')->nullable();
            $table->date('date_acquired');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
