<?php

namespace App\Actions\Order;

use App\Enums\OrderStatus;
use App\Enums\StockMovementType;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidOrderAction
{
    /**
     * Membatalkan pesanan & mengembalikan seluruh stok barang secara atomik.
     */
    public function execute(Order $order, User $manager, string $reason): Order
    {
        if ($order->status === OrderStatus::Voided) {
            throw ValidationException::withMessages([
                'order' => ['Pesanan ini sudah dibatalkan sebelumnya.'],
            ]);
        }

        return DB::transaction(function () use ($order, $manager, $reason) {
            // 1. Ubah status pesanan menjadi Voided
            $order->update([
                'status' => OrderStatus::Voided,
                'notes' => trim(($order->notes ? $order->notes.' | ' : '')."VOID oleh {$manager->name}: {$reason}"),
            ]);

            // 2. Kembalikan stok untuk setiap item di pesanan
            $order->load('items.product');

            foreach ($order->items as $item) {
                if (! $item->product_id) {
                    continue;
                }

                /** @var Product $product */
                $product = Product::where('id', $item->product_id)
                    ->lockForUpdate()
                    ->first();
                if (! $product) {
                    continue;
                }

                $stockBefore = $product->stock;
                $stockAfter = $stockBefore + $item->quantity;

                // kembalikan stok fisik
                $product->update([
                    'stock' => $stockAfter,
                ]);

                // Catat audit pengembalian barang (Return)
                StockMovement::create([
                    'product_id' => $product->id,
                    'user_id' => $manager->id,
                    'type' => StockMovementType::ReturnItem,
                    'quantity' => $item->quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'reference_type' => Order::class,
                    'reference_id' => $order->id,
                    'notes' => "Pengembalian stok dari Void faktur: {$order->invoice_number} (Alasan: {$reason})",
                ]);
            }

            return $order->fresh(['cashier', 'items.product', 'payments']);
        });
    }
}
