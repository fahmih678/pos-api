<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    /**
     * Menampilkan daftar produk dengan filter pencarian cepat untuk kasir.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()
            ->with('category') // Eager load untuk hindari query N+1
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->integer('category_id')))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            // Filter pencarian nama / SKU / Barcode untuk kasir
            ->when($request->filled('q'), function ($q) use ($request) {
                $search = $request->string('q')->toString();
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%")
                        ->orWhere('barcode', $search);
                });
            })
            ->orderByDesc('id'); // Pengurutan deterministik
        $perPage = min($request->integer('per_page', 15), 100);

        return ProductResource::collection($query->paginate($perPage));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return response()->json([
            'message' => 'Produk berhasil ditambahkan.',
            'data' => new ProductResource($product->load('category')),
        ], 201);
    }

    public function show(Product $product): JsonResponse
    {
        return response()->json([
            'data' => new ProductResource($product->load('category')),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->validated());

        return response()->json([
            'message' => 'Produk berhasil diperbarui.',
            'data' => new ProductResource($product->load('category')),
        ]);
    }

    public function destroy(Request $request, Product $product): JsonResponse
    {
        Gate::authorize('delete', $product);
        $product->delete();

        return response()->json([
            'message' => 'Produk berhasil dihapus.',
        ]);
    }

    /**
     * Menampilkan daftar produk yang stoknya di bawah batas minimal.
     */
    public function lowStock(): AnonymousResourceCollection
    {
        $products = Product::query()
            ->with('category')
            ->lowStock() // Menggunakan scopeLowStock yang sudah kita buat sebelumnya
            ->orderBy('stock')
            ->paginate(20);

        return ProductResource::collection($products);
    }
}
