<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Restock = 'restock';           // Barang masuk dari supplier
    case Adjusment = 'adjustment';      // Penyesuaian stok fisik (stok opname)
    case Damage = 'damage';             // Barang rusak / pecah / kadaluarsa
    case ReturnItem = 'return';         // Pengembalian dari pelanggan
    case Sale = 'sale';                 // Terjual saat transaksi POS

    public function label(): string
    {
        return match ($this) {
            self::Restock => 'Barang Masuk (Restock)',
            self::Adjusment => 'Penyesuaian (Opname)',
            self::Damage => 'Barang Rusak',
            self::ReturnItem => 'Retur Pelanggan',
            self::Sale => 'Penjualan Kasir',
        };
    }
}
