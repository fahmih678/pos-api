<?php

namespace App\Http\Requests\Inventory;

use App\Enums\StockMovementType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StockAdjustmentRequest extends FormRequest
{
    /**
     * Hanya Admin & Manager yang boleh mengubah stok manual di luar transaksi kasir
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() || $this->user()?->isManager();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Tipe mutasi selain 'sale' (karena sale hanya dipicu saat kasir checkout)
            'type' => [
                'required',
                Rule::enum(StockMovementType::class)->except([StockMovementType::Sale->value]),
            ],
            'quantity' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
