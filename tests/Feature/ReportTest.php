<?php

use App\Actions\Order\ProcessCheckoutAction;
use App\Actions\Payment\ProcessPaymentAction;
use App\Enums\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('admin dan manager dapat melihat ringkasan omzet dan laba kotor toko secara akurat', function () {
    $admin = User::factory()->admin()->create();
    $cashier = User::factory()->cashier()->create();

    // Produk A: Modal 10.000, Jual 20.000 (Profit per item = 10.000)
    $productA = Product::factory()->create([
        'cost_price' => 10000,
        'selling_price' => 20000,
        'stock' => 50,
    ]);

    // Checkout: Beli 2 unit Product A (Omzet = 40.000, Modal HPP = 20.000, Laba Kotor = 20.000)
    $order = app(ProcessCheckoutAction::class)->execute(
        cashier: $cashier,
        items: [['product_id' => $productA->id, 'quantity' => 2]],
    );

    // Kasir terima pembayaran tunai Rp 40.000
    app(ProcessPaymentAction::class)->execute(
        order: $order,
        method: PaymentMethod::Cash,
        amountTendered: 40000,
    );

    // Admin mengecek laporan
    Sanctum::actingAs($admin);
    $response = $this->getJson('/api/reports/sales-summary');

    $response->assertOk()
        ->assertJsonPath('data.summary.total_orders', 1)
        ->assertJsonPath('data.summary.total_revenue', 40000)
        ->assertJsonPath('data.summary.total_cogs_modal', 20000)
        ->assertJsonPath('data.summary.gross_profit', 20000)
        ->assertJsonPath('data.payment_methods_breakdown.0.payment_method', PaymentMethod::Cash->value)
        ->assertJsonPath('data.payment_methods_breakdown.0.total_amount', 40000);
});

test('kasir dilarang (403 Forbidden) mengakses laporan penjualan dan keuangan', function () {
    $cashier = User::factory()->cashier()->create();
    Sanctum::actingAs($cashier);

    // Coba akses sales-summary -> Ditolak
    $this->getJson('/api/reports/sales-summary')->assertForbidden();

    // Coba akses top-products -> Ditolak
    $this->getJson('/api/reports/top-products')->assertForbidden();
});

test('admin dapat melihat produk terlaris (top selling products) berurutan', function () {
    $admin = User::factory()->admin()->create();
    $cashier = User::factory()->cashier()->create();

    $productA = Product::factory()->create(['name' => 'Kopi Favorit', 'stock' => 50]);
    $productB = Product::factory()->create(['name' => 'Kue Kurang Laku', 'stock' => 50]);

    // Transaksi: Beli 5 unit Kopi Favorit dan 1 unit Kue
    app(ProcessCheckoutAction::class)->execute(
        cashier: $cashier,
        items: [
            ['product_id' => $productA->id, 'quantity' => 5],
            ['product_id' => $productB->id, 'quantity' => 1],
        ],
    );

    Sanctum::actingAs($admin);
    $response = $this->getJson('/api/reports/top-products?limit=5');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.product_name', 'Kopi Favorit')
        ->assertJsonPath('data.0.total_quantity_sold', 5)
        ->assertJsonPath('data.1.product_name', 'Kue Kurang Laku')
        ->assertJsonPath('data.1.total_quantity_sold', 1);
});
