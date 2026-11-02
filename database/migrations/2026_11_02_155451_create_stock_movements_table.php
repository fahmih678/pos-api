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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type'); // StockMovementType enum value
            $table->integer('quantity'); // Jumlah perubahan (+ atau -)
            $table->integer('stock_before');
            $table->integer('stock_after');

            // Kolom referensi opsional jika berasa dari Order ID
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            // Index untuk mempercepat query laporan kartu stock
            $table->index(['product_id', 'created_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
