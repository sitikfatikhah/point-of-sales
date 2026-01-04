<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Restructure product_transaction_details menjadi ledger utama untuk semua pergerakan stok:
     * - Purchase (masuk dari pembelian)
     * - Sale/Transaction (keluar dari penjualan)
     * - Adjustment (koreksi manual dengan nomor jurnal)
     */
    public function up(): void
    {
        // Drop tabel lama
        Schema::dropIfExists('product_transaction_detail');

        // Buat tabel baru sebagai stock ledger
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Jenis pergerakan: purchase, sale, adjustment_in, adjustment_out, return, damage, correction
            // Menggunakan string untuk kompatibilitas SQLite
            $table->string('movement_type'); // purchase, sale, adjustment_in, adjustment_out, return, damage, correction

            // Referensi ke source (purchase_id, transaction_id, atau adjustment_id)
            $table->string('reference_type')->nullable(); // 'purchase', 'transaction', 'adjustment'
            $table->unsignedBigInteger('reference_id')->nullable();

            // Detail pergerakan
            $table->decimal('quantity', 15, 2); // Positif untuk masuk, negatif untuk keluar
            $table->decimal('unit_price', 15, 2)->default(0); // Harga satuan saat transaksi
            $table->decimal('total_price', 15, 2)->default(0); // Total harga (quantity * unit_price)

            // Tracking stok
            $table->decimal('quantity_before', 15, 2)->default(0);
            $table->decimal('quantity_after', 15, 2)->default(0);

            // Metadata
            $table->string('journal_number')->nullable(); // Nomor jurnal untuk adjustment
            $table->text('notes')->nullable();

            $table->timestamps();

            // Index untuk performa
            $table->index(['product_id', 'movement_type']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('journal_number');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');

        // Recreate old pivot table
        Schema::create('product_transaction_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_detail_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }
};
