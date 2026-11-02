<?php

namespace App\Http\Controllers\Api;

use App\Actions\Inventory\AdjustStockAction;
use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockAdjustmentRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockAdjustmentController extends Controller
{
    /**
     * Melihat riwayat mutasi (kartu stok) suatu produk.
     */
    public function index(Product $product): AnonymousResourceCollection
    {
        $movements = $product->stockMovements()
            ->with('user')
            ->orderByDesc('id')
            ->paginate(20);

        return StockMovementResource::collection($movements);
    }

    /**
     * Melakukan penyesuaian stok (Restock, Opname, Barang Rusak).
     */
    public function store(
        StockAdjustmentRequest $request,
        Product $product,
        AdjustStockAction $action,
    ): JsonResponse {
        $movement = $action->execute(
            product: $product,
            type: $request->enum('type', StockMovementType::class),
            quantity: $request->integer('quantity'),
            user: $request->user(),
            notes: $request->input('notes'),
        );

        return response()->json([
            'message' => 'Stock product berhasil diperbarui.',
            'data' => new StockMovementResource($movement->load('user')),
        ], 201);
    }
}
