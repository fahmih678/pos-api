<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
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
            'product_id' => $this->product_id,
            'product_name' => $this->product_name_snapshot,
            'unit_selling_price' => (float) $this->unit_selling_price,

            // HPP snapshot hanya boleh dilihat Admin/Manager
            'unit_cost_price' => $this->when(
                $user && ($user->isAdmin() || $user->isManager()),
                (float) $this->unit_cost_price
            ),

            'quantity' => $this->quantity,
            'subtotal' => (float) $this->subtotal,
        ];
    }
}
