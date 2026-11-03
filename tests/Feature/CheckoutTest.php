<?php

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('kasir berhasil melakukan checkout pesanan multi-item secara atomik', function () {
    $cashier = User::factory()->cashier()->create();
    Sanctum::actingAs($cashier);

    $productA = Product::factory()->create([
        'name' => 'Kopi Latte',
        'selling_price' => 20000,
        'cost_price' => 8000,
        'stock' => 50,
    ]);

    $productB = Product::factory()->create([
        'name' => 'Croissant Coklat',
        'selling_price' => 15000,
        'cost_price' => 6000,
        'stock' => 30,
    ]);

    $payload = [
        'customer_name' => 'Budi Santoso',
        'discount_amount' => 5000,
        'tax_amount' => 2000,
        'notes' => 'Meja 05',
        'items' => [
            ['product_id' => $productA->id, 'quantity' => 2], // 40.000
            ['product_id' => $productB->id, 'quantity' => 1], // 15.000
        ],
    ];

    $response = $this->postJson('/api/checkout', $payload);

    // 1. Verifikasi Response JSON
    $response->assertCreated()
        ->assertJsonPath('data.customer_name', 'Budi Santoso')
        ->assertJsonPath('data.subtotal', 55000)
        ->assertJsonPath('data.discount_amount', 5000)
        ->assertJsonPath('data.tax_amount', 2000)
        ->assertJsonPath('data.grand_total', 52000) // 55.000 - 5.000 + 2.000
        ->assertJsonPath('data.status', OrderStatus::Completed->value);

    // 2. Verifikasi Pemotongan Stok Produk
    expect($productA->fresh()->stock)->toBe(48)
        ->and($productB->fresh()->stock)->toBe(29);

    // 3. Verifikasi Mutasi Stok Tercatat
    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $productA->id,
        'type' => StockMovementType::Sale->value,
        'quantity' => -2,
    ]);

    // 4. Verifikasi Price Snapshotting
    $order = Order::first();
    expect($order->items)->toHaveCount(2);
    expect($order->items->first()->product_name_snapshot)->toBe('Kopi Latte');
});

test('checkout dibatalkan secara utuh (rollback) jika salah satu barang stoknya tidak mencukupi', function () {
    $cashier = User::factory()->cashier()->create();
    Sanctum::actingAs($cashier);

    $productA = Product::factory()->create(['stock' => 20]);
    $productB = Product::factory()->create(['name' => 'Roti Habis', 'stock' => 1]);

    $payload = [
        'items' => [
            ['product_id' => $productA->id, 'quantity' => 5],
            ['product_id' => $productB->id, 'quantity' => 5], // Stok tidak cukup!
        ],
    ];

    $response = $this->postJson('/api/checkout', $payload);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);

    // Pastikan tidak ada order yang terbuat
    expect(Order::count())->toBe(0);

    // Pastikan stok Product A tidak ikut berkurang (Rollback ACID terjaga)
    expect($productA->fresh()->stock)->toBe(20);
});

test('perubahan harga produk di masa depan tidak mengubah harga struk masa lampau (price snapshotting)', function () {
    $cashier = User::factory()->cashier()->create();
    Sanctum::actingAs($cashier);

    $product = Product::factory()->create([
        'name' => 'Kopi Spesial',
        'selling_price' => 25000,
        'stock' => 10,
    ]);

    // Kasir checkout hari ini
    $this->postJson('/api/checkout', [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertCreated();

    // Bulan depan harga produk naik menjadi Rp 35.000
    $product->update(['selling_price' => 35000]);

    // Struk order masa lalu tetap mencatat harga Rp 25.000
    $order = Order::first();
    $item = $order->items->first();
    expect((float) $item->unit_selling_price)->toBe(25000.0);
});

test('kasir dapat melihat riwayat dan detail faktur penjualan', function () {
    $cashier = User::factory()->cashier()->create();
    Sanctum::actingAs($cashier);

    $product = Product::factory()->create(['stock' => 10]);

    $checkoutRes = $this->postJson('/api/checkout', [
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ])->assertCreated();

    $orderId = $checkoutRes->json('data.id');

    // Index
    $this->getJson('/api/orders')
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // Show Detail
    $this->getJson("/api/orders/{$orderId}")
        ->assertOk()
        ->assertJsonPath('data.id', $orderId);
});
