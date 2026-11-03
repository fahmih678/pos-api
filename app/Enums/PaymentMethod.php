<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Qris = 'qris';
    case DebitCard = 'debit_card';
    case CreditCard = 'credit_card';
    case BankTransfer = 'bank_transfer';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai (Cash)',
            self::Qris => 'QRIS',
            self::DebitCard => 'Kartu Debit',
            self::CreditCard => 'Kartu Kredit',
            self::BankTransfer => 'Transfer Bank',
        };
    }
}
