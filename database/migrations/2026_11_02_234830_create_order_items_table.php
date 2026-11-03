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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();

            // Price Snapshotting (agar perubahan transaksi lampau tidak terjadi)
            $table->string('product_name_snapshot');
            $table->decimal('unit_cost_price', 12, 2);    // Snapshot modal
            $table->decimal('unit_selling_price', 12, 2); // Snapshot harga jual

            $table->integer('quantity');
            $table->decimal('subtotal', 12, 2); // unit_selling_price * quantity
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
