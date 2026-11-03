<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('payment_method')->default(PaymentMethod::Cash->value);
            $table->decimal('amount_tendered', 12, 2); // Jumlah uang yang dibayarkan
            $table->decimal('change_due', 12, 2)->default(0); // Kembalian
            $table->string('reference_number')->nullable(); // Kode approval QRIS/EDC

            $table->string('status')->default(PaymentStatus::Success->value);
            $table->timestamps();
            $table->index('order_id');
            $table->index('payment_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
