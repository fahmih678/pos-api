<?php

namespace App\Actions\Payment;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Validation\ValidationException;

class ProcessPaymentAction
{
    public function execute(
        Order $order,
        PaymentMethod $method,
        float $amountTendered = 0,
        ?string $referenceNumber = null,
    ): Payment {
        if ($order->status === OrderStatus::Voided) {
            throw ValidationException::withMessages([
                'order' => ['Tidak dapat memproses pembayaran untuk pesanan yang sudah dibatalkan (void).'],
            ]);
        }

        $grandTotal = (float) $order->grand_total;
        if ($method === PaymentMethod::Cash) {
            if ($amountTendered < $grandTotal) {
                throw ValidationException::withMessages([
                    'amount_tendered' => ['Uang tunai kurang. Total Tagihan: Rp '.number_format($grandTotal, 0, ',', '.').'.'],
                ]);
            }
            $changeDue = $amountTendered - $grandTotal;
        } else {
            // untuk pembayaran non-tunai (QRIS, Kartu Debit, Transfer)
            $amountTendered = $grandTotal;
            $changeDue = 0;

            if (empty($referenceNumber)) {
                throw ValidationException::withMessages([
                    'reference_number' => ['Nomor referensi / approval wajib diisi untuk transaksi non-tunai.'],
                ]);
            }
        }

        return Payment::create([
            'order_id' => $order->id,
            'payment_method' => $method,
            'amount_tendered' => $amountTendered,
            'change_due' => $changeDue,
            'reference_number' => $referenceNumber,
            'status' => PaymentStatus::Success,
        ]);
    }
}
