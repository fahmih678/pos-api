<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Pembayaran',
            self::Completed => 'Selesai',
            self::Voided => 'Dibatalkan (void)',
        };
    }
}
