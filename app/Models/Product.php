<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'category_id',
    'sku',
    'barcode',
    'name',
    'description',
    'cost_price',
    'selling_price',
    'stock',
    'alert_stock_threshold',
    'is_active',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'stock' => 'integer',
            'alert_stock_threshold' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereColumn('stock', '<=', 'alert_stock_threshold');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
