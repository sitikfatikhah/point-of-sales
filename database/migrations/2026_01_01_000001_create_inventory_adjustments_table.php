<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();

            // Nomor jurnal adjustment (required untuk semua adjustment)
            $table->string('journal_number')->nullable();

            $table->foreignId('product_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Type hanya untuk adjustment manual (tanpa purchase/sale)
            $table->string('type')->default('adjustment_in'); // adjustment_in, adjustment_out, return, damage, correction

            $table->decimal('quantity_change', 15, 2)->default(0);

            $table->text('reason')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('journal_number');
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
