<?php

namespace App\Actions\Order;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProcessCheckoutAction
{
    /**
     * Memproses checkout transaksi kasir secara aman dan atomik.
     *
     * @param  array<int, array{product_id: int, quantity: int}>  $items
     */
    public function execute(
        User $cashier,
        array $items,
        float $discountAmount = 0,
        float $taxAmount = 0,
        ?string $customerName = null,
        ?string $notes = null,
    ): Order {
        return DB::transaction(function () use ($cashier, $items, $discountAmount, $taxAmount, $customerName, $notes) {
            // 1. Kumpulkan semua ID product & urutan (sort) untuk mencegah Deadlock database
            $itemQuantities = [];
            foreach ($items as $item) {
                $productId = (int) $item['product_id'];
                $itemQuantities[$productId] = ($itemQuantities[$productId] ?? 0) + (int) $item['quantity'];
            }

            $productIds = array_keys($itemQuantities);
            sort($productIds); // Kunci urutan deterministik

            // 2. Lock semua produk yang dibeli secara pesimistik
            $products = Product::whereIn('id', $productIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // 3. Validasi kebenaran produk, status aktif, dan ketersedian stok
            $subtotal = 0;
            $orderItemsData = [];

            foreach ($itemQuantities as $productId => $qty) {
                /** @var Product|null $product */
                $product = $products->get($productId);

                if (! $product || ! $product->is_active) {
                    throw ValidationException::withMessages([
                        'items' => ["Produk dengan ID {$productId} tidak ditemukan atau sedang tidak aktif"],
                    ]);
                }

                if ($product->stock < $qty) {
                    throw ValidationException::withMessages([
                        'items' => ["Stok produk '{$product->name}' tidak mencukupi (tersedia: {$product->stock}, diminta: {$qty})."],
                    ]);
                }

                $itemSubtotal = (float) $product->selling_price * $qty;
                $subtotal += $itemSubtotal;

                $orderItemsData[] = [
                    'product' => $product,
                    'quantity' => $qty,
                    'unit_cost_price' => $product->cost_price,
                    'unit_selling_price' => $product->selling_price,
                    'subtotal' => $itemSubtotal,
                ];
            }

            // 4. Hitung Grand Total
            $discount = min($discountAmount, $subtotal);
            $grandTotal = max(0, $subtotal - $discount + $taxAmount);

            // 5. Generate Nomor Faktur Unik (INV-YYYYMMDD-XXXX)
            $invoiceNumber = $this->generateInvoiceNumber();

            // 6. Buat Record Order
            $order = Order::create([
                'invoice_number' => $invoiceNumber,
                'cashier_id' => $cashier->id,
                'customer_name' => $customerName,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
                'status' => OrderStatus::Completed,
                'notes' => $notes,
            ]);

            // 7. Simpan Order Items, potong stok produk, dan buat catatan audit StockMovement
            foreach ($orderItemsData as $data) {
                /** @var Product $product */
                $product = $data['product'];
                $qty = $data['quantity'];
                $stockBefore = $product->stock;
                $stockAfter = $stockBefore - $qty;

                // Simpan snapshot item
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name_snapshot' => $product->name,
                    'unit_cost_price' => $data['unit_cost_price'],
                    'unit_selling_price' => $data['unit_selling_price'],
                    'quantity' => $qty,
                    'subtotal' => $data['subtotal'],
                ]);

                // Kurangi stok produk
                $product->update([
                    'stock' => $stockAfter,
                ]);

                // Audit Trail Mutasi Stok Penjualan
                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $cashier->id,
                    'type' => StockMovementType::Sale,
                    'quantity' => -$qty,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'notes' => "Penjualan kasir faktur: {$invoiceNumber}",
                ]);
            }

            return $order->load(['cashier', 'items.product']);
        });
    }

    /**
     * Generate nomor invoice berurutan per hari: INV-YYYYMMDD-0001
     */
    private function generateInvoiceNumber(): string
    {
        $today = now()->format('Ymd');
        $prefix = "INV-{$today}-";

        $latestOrder = Order::where('invoice_number', 'like', "{$prefix}%")
            ->OrderByDesc('id')
            ->lockForUpdate()
            ->first();

        $sequence = 1;
        if ($latestOrder) {
            $lastSequence = (int) substr($latestOrder->invoice_number, -4);
            $sequence = $lastSequence + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
