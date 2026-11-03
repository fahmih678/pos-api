<?php

namespace Database\Seeders;

use App\Actions\Order\ProcessCheckoutAction;
use App\Actions\Payment\ProcessPaymentAction;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // 1. Buat Akun Pengguna (RBAC)
        $admin = User::firstOrCreate(
            ['email' => 'admin@pos.test'],
            [
                'name' => 'Store Owner (Admin)',
                'password' => 'password',
                'role' => UserRole::ADMIN,
                'is_active' => true,
            ]
        );

        $manager = User::firstOrCreate(
            ['email' => 'manager@pos.test'],
            [
                'name' => 'Store Supervisor (Manager)',
                'password' => 'password',
                'role' => UserRole::MANAGER,
                'is_active' => true,
            ]
        );

        $cashier = User::firstOrCreate(
            ['email' => 'cashier@pos.test'],
            [
                'name' => 'Kasir Utama',
                'password' => 'password',
                'role' => UserRole::CASHIER,
                'is_active' => true,
            ]
        );

        // 2. Buat Kategori Produk
        $catBeverage = Category::firstOrCreate(['slug' => 'minuman-kopi'], [
            'name' => 'Minuman Kopi',
            'description' => 'Aneka kopi espresso dan olahan susu',
            'is_active' => true,
        ]);

        $catFood = Category::firstOrCreate(['slug' => 'pastry-dan-makanan'], [
            'name' => 'Pastry & Makanan',
            'description' => 'Roti, pastry, dan makanan pendamping',
            'is_active' => true,
        ]);

        // 3. Buat Produk Realistis
        $prod1 = Product::firstOrCreate(['sku' => 'PRD-KOPI-001'], [
            'category_id' => $catBeverage->id,
            'barcode' => '8991234567890',
            'name' => 'Kopi Susu Gula Aren 250ml',
            'description' => 'Espresso dengan gula aren premium',
            'cost_price' => 8000,
            'selling_price' => 18000,
            'stock' => 100,
            'alert_stock_threshold' => 15,
            'is_active' => true,
        ]);

        $prod2 = Product::firstOrCreate(['sku' => 'PRD-KOPI-002'], [
            'category_id' => $catBeverage->id,
            'barcode' => '8991234567891',
            'name' => 'Americano Iced',
            'description' => 'Double shot espresso dingin',
            'cost_price' => 5000,
            'selling_price' => 15000,
            'stock' => 80,
            'alert_stock_threshold' => 10,
            'is_active' => true,
        ]);

        $prod3 = Product::firstOrCreate(['sku' => 'PRD-ROTI-001'], [
            'category_id' => $catFood->id,
            'barcode' => '8992234567890',
            'name' => 'Butter Croissant',
            'description' => 'Croissant renyah dengan butter Prancis',
            'cost_price' => 10000,
            'selling_price' => 22000,
            'stock' => 30,
            'alert_stock_threshold' => 5,
            'is_active' => true,
        ]);

        // Produk Stok Menipis (untuk testing endpoint low-stock)
        Product::firstOrCreate(['sku' => 'PRD-SNACK-LOW'], [
            'category_id' => $catFood->id,
            'barcode' => '8993234567899',
            'name' => 'Cookies Coklat (Stok Tipis)',
            'description' => 'Cookies lezat stok hampir habis',
            'cost_price' => 7000,
            'selling_price' => 16000,
            'stock' => 3,
            'alert_stock_threshold' => 10,
            'is_active' => true,
        ]);

        // 4. Buat Contoh Transaksi Penjualan Perdana
        $order = app(ProcessCheckoutAction::class)->execute(
            cashier: $cashier,
            items: [
                ['product_id' => $prod1->id, 'quantity' => 2],
                ['product_id' => $prod3->id, 'quantity' => 1],
            ],
            discountAmount: 3000,
            taxAmount: 2000,
            customerName: 'Pelanggan Setia #1',
            notes: 'Dine-in meja 01',
        );

        // Proses Pembayaran Tunai
        app(ProcessPaymentAction::class)->execute(
            order: $order,
            method: PaymentMethod::Cash,
            amountTendered: 60000,
        );
    }
}
