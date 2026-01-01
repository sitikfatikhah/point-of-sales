<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable()->unique();
            $table->date('purchase_date');
            $table->string('supplier_name')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('tax_included')->default(false);
            $table->string('status')->default('ended');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('purchases');
        Schema::enableForeignKeyConstraints();
    }
};
