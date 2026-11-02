<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Semua staf yang aktif (Kasir, Manager, Admin) boleh melihat daftar produk.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Semua staf yang aktif boleh melihat detail produk.
     */
    public function view(User $user, Product $product): bool
    {
        return $user->is_active;
    }

    /**
     * Hanya Admin dan Manager yang boleh membuat produk baru.
     */
    public function create(User $user): bool
    {
        return $user->is_active && ($user->isAdmin() || $user->isManager());
    }

    /**
     * Hanya Admin dan Manager yang boleh mengupdate produk.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->is_active && ($user->isAdmin() || $user->isManager());
    }

    /**
     * Hanya Admin yang boleh menghapus produk.
     */
    public function delete(User $user, Product $product): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Product $product): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        return false;
    }
}
