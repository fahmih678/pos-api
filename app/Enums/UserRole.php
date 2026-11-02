<?php

namespace App\Enums;

enum UserRole: string
{
    // case NAMEINAPP = 'name-in-database';
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case CASHIER = 'cashier';

    // extra helper to allow for greater customization of displayed values, without disclosing the name/value data directly
    public function label(): string
    {
        return match ($this) {
            static::ADMIN => 'Admin',
            static::MANAGER => 'Manager',
            static::CASHIER => 'Cashier'
        };
    }
}
