<?php

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('manager dapat melakukan restock barang dan menambah stok produk secara akurat', function () {
    $manager = User::factory()->manager()->create();
    Sanctum::actingAs($manager);

    $product = Product::factory()->create([
        'stock' => 20,
    ]);

    $response = $this->postJson("/api/products/{$product->id}/stock-adjustments", [
        'type' => StockMovementType::Restock->value,
        'quantity' => 30,
        'notes' => 'Penerimaan stok dari supplier',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.stock_before', 20)
        ->assertJsonPath('data.stock_after', 50)
        ->assertJsonPath('data.quantity', 30);

    expect($product->fresh()->stock)->toBe(50);
});

test('manager dapat mencatat barang rusak (damage) dan mengurangi stok', function () {
    $manager = User::factory()->manager()->create();
    Sanctum::actingAs($manager);

    $product = Product::factory()->create([
        'stock' => 15,
    ]);

    $response = $this->postJson("/api/products/{$product->id}/stock-adjustments", [
        'type' => StockMovementType::Damage->value,
        'quantity' => 3,
        'notes' => 'Barang pecah saat display di rak',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.stock_before', 15)
        ->assertJsonPath('data.stock_after', 12)
        ->assertJsonPath('data.quantity', -3);

    expect($product->fresh()->stock)->toBe(12);
});

test('sistem menolak jika pengurangan stok melebihi stok fisik yang tersedia (mencegah stok minus)', function () {
    $manager = User::factory()->manager()->create();
    Sanctum::actingAs($manager);

    $product = Product::factory()->create([
        'stock' => 5,
    ]);

    $response = $this->postJson("/api/products/{$product->id}/stock-adjustments", [
        'type' => StockMovementType::Damage->value,
        'quantity' => 10, // Melebihi stok yang ada
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['quantity']);

    // Pastikan stok tidak berubah di database
    expect($product->fresh()->stock)->toBe(5);
});

test('kasir dilarang (403 Forbidden) mengubah stok secara manual', function () {
    $cashier = User::factory()->cashier()->create();
    Sanctum::actingAs($cashier);

    $product = Product::factory()->create(['stock' => 10]);

    $response = $this->postJson("/api/products/{$product->id}/stock-adjustments", [
        'type' => StockMovementType::Restock->value,
        'quantity' => 10,
    ]);

    $response->assertForbidden();
});

test('endpoint low-stock hanya menampilkan produk yang stoknya di bawah batas peringatan', function () {
    $cashier = User::factory()->cashier()->create();
    Sanctum::actingAs($cashier);

    // Produk stok aman
    Product::factory()->create([
        'name' => 'Kopi Aman',
        'stock' => 50,
        'alert_stock_threshold' => 10,
    ]);

    // Produk stok menipis
    Product::factory()->create([
        'name' => 'Gula Menipis',
        'stock' => 3,
        'alert_stock_threshold' => 10,
    ]);

    $response = $this->getJson('/api/products/low-stock');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Gula Menipis');
});
