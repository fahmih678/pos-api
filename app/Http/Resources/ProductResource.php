<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'description' => $this->description,

            // Harga Jual (dapat dilihat kasir)
            'selling_price' => (float) $this->selling_price,

            // HPP / Harga Modal (Hanya terlihat jika Admin / Manager)
            'cost_price' => $this->when(
                $user && ($user->isAdmin() || $user->isManager()),
                (float) $this->cost_price
            ),
            'stock' => $this->stock,
            'alert_stock_threshold' => $this->alert_stock_threshold,
            'is_low_stock' => $this->stock <= $this->alert_stock_threshold,
            'is_active' => $this->is_active,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
