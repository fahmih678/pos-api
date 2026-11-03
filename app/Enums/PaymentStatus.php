<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Success = 'success';
    case Pending = 'pending';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Berhasil',
            self::Pending => 'Menunggu Pembayaran',
            self::Failed => 'Gagal',
        };
    }
}
