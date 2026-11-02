<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('kasir dapat melihat daftar produk, namun harga modal (cost_price) disembunyikan demi privasi keuangan', function () {
    $cashier = User::factory()->cashier()->create();
    Sanctum::actingAs($cashier);

    $product = Product::factory()->create([
        'cost_price' => 15000,
        'selling_price' => 25000,
    ]);

    $response = $this->getJson('/api/products');

    $response->assertOk()
        ->assertJsonFragment(['selling_price' => 25000])
        ->assertJsonMissing(['cost_price' => 15000]); // Kasir tidak boleh melihat HPP
});

test('admin dan manager dapat melihat harga modal (cost_price)', function () {
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);

    Product::factory()->create([
        'cost_price' => 15000,
        'selling_price' => 25000,
    ]);

    $response = $this->getJson('/api/products');

    $response->assertOk()
        ->assertJsonFragment(['cost_price' => 15000]);
});

test('kasir dilarang (403 Forbidden) menambah produk baru', function () {
    $cashier = User::factory()->cashier()->create();
    Sanctum::actingAs($cashier);

    $category = Category::factory()->create();

    $response = $this->postJson('/api/products', [
        'category_id' => $category->id,
        'sku' => 'PRD-TEST-01',
        'name' => 'Kopi Susu Gula Aren',
        'cost_price' => 10000,
        'selling_price' => 18000,
    ]);

    $response->assertForbidden();
});

test('admin dan manager berhasil menambah produk baru dengan validasi lengkap', function () {
    $manager = User::factory()->manager()->create();
    Sanctum::actingAs($manager);

    $category = Category::factory()->create();

    $response = $this->postJson('/api/products', [
        'category_id' => $category->id,
        'sku' => 'PRD-KOPI-01',
        'barcode' => '8991234567890',
        'name' => 'Kopi Susu Espresso',
        'cost_price' => 8000,
        'selling_price' => 15000,
        'stock' => 50,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.sku', 'PRD-KOPI-01')
        ->assertJsonPath('data.stock', 50);

    $this->assertDatabaseHas('products', [
        'sku' => 'PRD-KOPI-01',
    ]);
});

test('kasir dapat mencari produk berdasarkan kata kunci barcode scanner atau nama', function () {
    $cashier = User::factory()->cashier()->create();
    Sanctum::actingAs($cashier);

    Product::factory()->create([
        'name' => 'Teh Botol Sosro',
        'barcode' => '8992222111100',
    ]);

    Product::factory()->create([
        'name' => 'Air Mineral Aqua',
        'barcode' => '8993333111100',
    ]);

    // Cari via Barcode scanner kasir
    $response = $this->getJson('/api/products?q=8992222111100');
    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Teh Botol Sosro');
});

test('hanya admin yang diizinkan menghapus produk', function () {
    $product = Product::factory()->create();

    // Kasir coba hapus -> ditolak (403)
    $cashier = User::factory()->cashier()->create();
    Sanctum::actingAs($cashier);
    $this->deleteJson("/api/products/{$product->id}")->assertForbidden();

    // Admin hapus -> berhasil (200)
    $admin = User::factory()->admin()->create();
    Sanctum::actingAs($admin);
    $this->deleteJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJson(['message' => 'Produk berhasil dihapus.']);

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});
