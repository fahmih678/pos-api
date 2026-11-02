<?php

namespace App\Actions\Inventory;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdjustStockAction
{
    /**
     * Eksekusi mutasi stok secara atomik dengan pessimistic locking.
     */
    public function execute(
        Product $product,
        StockMovementType $type,
        int $quantity,
        ?User $user = null,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): StockMovement {
        return DB::transaction(function () use ($product, $type, $quantity, $user, $notes, $referenceType, $referenceId) {
            // Lock row produk secara pesimistik agar aman dari race condition
            /** @var Product $lockedProduct */
            $lockedProduct = Product::where('id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $stockBefore = $lockedProduct->stock;

            // Hitung stok baru berdasarkan tipe mutasi
            $newStock = match ($type) {
                StockMovementType::Restock,
                StockMovementType::ReturnItem => $stockBefore + $quantity,
                StockMovementType::Damage,
                StockMovementType::Sale => $stockBefore - $quantity,
                StockMovementType::Adjusment => $quantity, // Set ke jumlah opman langsung
            };

            // Safeguard: Stok fisik di toko tidak boleh menjadi minus
            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'quantity' => "Pengurangan stok melebihi stok yang tersedia. Stok saat ini: {$stockBefore}).",
                ]);
            }

            // Simpan stok baru ke tabel products
            $lockedProduct->update([
                'stock' => $newStock,
            ]);

            // Hitung selisih mutasi kuantitas untuk audit trail
            $movementQuantity = match ($type) {
                StockMovementType::Adjusment => $newStock - $stockBefore,
                StockMovementType::Damage,
                StockMovementType::Sale => -$quantity,
                default => $quantity,
            };

            // Catat ke kartu stok (audit log)
            return StockMovement::create([
                'product_id' => $lockedProduct->id,
                'user_id' => $user?->id,
                'type' => $type,
                'quantity' => $movementQuantity,
                'stock_before' => $stockBefore,
                'stock_after' => $newStock,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'notes' => $notes,
            ]);
        });
    }
}
