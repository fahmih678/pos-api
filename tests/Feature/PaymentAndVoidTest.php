<?php

use App\Actions\Order\ProcessCheckoutAction;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('kasir dapat memproses pembayaran tunai dan kembalian dihitung secara akurat', function () {
    $cashier = User::factory()->cashier()->create();
    Sanctum::actingAs($cashier);

    $product = Product::factory()->create([
        'selling_price' => 20000,
        'stock' => 10,
    ]);

    // Checkout: total Rp 40.000
    $order = app(ProcessCheckoutAction::class)->execute(
        cashier: $cashier,
        items: [['product_id' => $product->id, 'quantity' => 2]],
    );

    // Kasir terima uang Rp 50.000 (Kembalian Rp 10.000)
    $response = $this->postJson("/api/orders/{$order->id}/pay", [
        'payment_method' => PaymentMethod::Cash->value,
        'amount_tendered' => 50000,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.payment_method', PaymentMethod::Cash->value)
        ->assertJsonPath('data.amount_tendered', 50000)
        ->assertJsonPath('data.change_due', 10000)
        ->assertJsonPath('data.status', PaymentStatus::Success->value);

    $this->assertDatabaseHas('payments', [
        'order_id' => $order->id,
        'payment_method' => PaymentMethod::Cash->value,
        'change_due' => 10000,
    ]);
});

test('pembayaran tunai ditolak jika uang yang diserahkan kurang dari tagihan', function () {
    $cashier = User::factory()->cashier()->create();
    Sanctum::actingAs($cashier);

    $product = Product::factory()->create([
        'selling_price' => 30000,
        'stock' => 10,
    ]);

    $order = app(ProcessCheckoutAction::class)->execute(
        cashier: $cashier,
        items: [['product_id' => $product->id, 'quantity' => 1]],
    );

    // Bayar Rp 20.000 (kurang Rp 10.000)
    $response = $this->postJson("/api/orders/{$order->id}/pay", [
        'payment_method' => PaymentMethod::Cash->value,
        'amount_tendered' => 20000,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['amount_tendered']);
});

test('pembayaran non-tunai (QRIS) mewajibkan nomor referensi dan kembalian bernilai 0', function () {
    $cashier = User::factory()->cashier()->create();
    Sanctum::actingAs($cashier);

    $product = Product::factory()->create([
        'selling_price' => 50000,
        'stock' => 10,
    ]);

    $order = app(ProcessCheckoutAction::class)->execute(
        cashier: $cashier,
        items: [['product_id' => $product->id, 'quantity' => 1]],
    );

    // 1. Gagal jika nomor referensi tidak diisi
    $this->postJson("/api/orders/{$order->id}/pay", [
        'payment_method' => PaymentMethod::Qris->value,
        'reference_number' => '',
    ])->assertUnprocessable()->assertJsonValidationErrors(['reference_number']);

    // 2. Berhasil jika nomor referensi approval diisi
    $response = $this->postJson("/api/orders/{$order->id}/pay", [
        'payment_method' => PaymentMethod::Qris->value,
        'reference_number' => 'QRIS-REF-998877',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.payment_method', PaymentMethod::Qris->value)
        ->assertJsonPath('data.reference_number', 'QRIS-REF-998877')
        ->assertJsonPath('data.change_due', 0);
});

test('manager dapat membatalkan pesanan (void) dan stok barang otomatis kembali utuh', function () {
    $cashier = User::factory()->cashier()->create();
    $manager = User::factory()->manager()->create();

    $productA = Product::factory()->create(['stock' => 20]);
    $productB = Product::factory()->create(['stock' => 15]);

    // Kasir checkout: Product A beli 3 (sisa 17), Product B beli 2 (sisa 13)
    $order = app(ProcessCheckoutAction::class)->execute(
        cashier: $cashier,
        items: [
            ['product_id' => $productA->id, 'quantity' => 3],
            ['product_id' => $productB->id, 'quantity' => 2],
        ],
    );

    expect($productA->fresh()->stock)->toBe(17)
        ->and($productB->fresh()->stock)->toBe(13);

    // Manager membatalkan pesanan (Void)
    Sanctum::actingAs($manager);
    $response = $this->postJson("/api/orders/{$order->id}/void", [
        'reason' => 'Pelanggan salah pesan item & minta ganti',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', OrderStatus::Voided->value);

    // 1. Verifikasi stok produk otomatis pulih ke nilai awal
    expect($productA->fresh()->stock)->toBe(20)
        ->and($productB->fresh()->stock)->toBe(15);

    // 2. Verifikasi audit trail pengembalian stok tercatat
    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $productA->id,
        'user_id' => $manager->id,
        'type' => StockMovementType::ReturnItem->value,
        'quantity' => 3,
    ]);
});

test('kasir dilarang (403 Forbidden) membatalkan pesanan / void', function () {
    $cashier = User::factory()->cashier()->create();

    $product = Product::factory()->create(['stock' => 10]);
    $order = app(ProcessCheckoutAction::class)->execute(
        cashier: $cashier,
        items: [['product_id' => $product->id, 'quantity' => 1]],
    );

    // Kasir mencoba void struk -> DITOLAK
    Sanctum::actingAs($cashier);
    $this->postJson("/api/orders/{$order->id}/void", [
        'reason' => 'Kasir batalkan sendiri',
    ])->assertForbidden();
});
