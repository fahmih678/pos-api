<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StockAdjustmentController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login']);

// Authenticated routes (Sanctum protected)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Peringatan Stok Menipis (diletakkan disini agar tidak tertimpa /products/{product})
    Route::get('/products/low-stock', [ProductController::class, 'lowStock']);

    // Master Data Catalog
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('products', ProductController::class);

    // Inventory Movements & Adjustments
    Route::get('/products/{product}/stock-movements', [StockAdjustmentController::class, 'index']);
    Route::post('/products/{product}/stock-adjustments', [StockAdjustmentController::class, 'store']);
});
