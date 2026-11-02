<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'quantity' => $this->quantity,
            'stock_before' => $this->stock_before,
            'stock_after' => $this->stock_after,
            'notes' => $this->notes,
            'stage_by' => $this->user?->name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
