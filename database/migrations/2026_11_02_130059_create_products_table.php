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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();

            // SKU & Barcode dibuat unik untuk scanner dan pencarian cepat
            $table->string('sku')->unique();
            $table->string('barcode')->nullable()->unique();

            $table->string('name');
            $table->text('description')->nullable();

            // Harga menggunakan decimal(12, 2) untuk mencegah floating point issue
            $table->decimal('cost_price', 12, 2); // HPP/ Modal
            $table->decimal('selling_price', 12, 2); // Harga Jual

            $table->integer('stock')->default(0);
            $table->integer('alert_stock_threshold')->default(10);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Composite index untuk optimasi filter kasir: "product aktif berdasarkan kategori"
            $table->index(['category_id', 'is_active']);
            $table->index('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
