<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    /**
     * Semua staf yang aktif boleh melihat detail.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Semua staf yang aktif boleh melihat detail produk.
     */
    public function view(User $user, Category $category): bool
    {
        return $user->is_active;
    }

    /**
     * Hanya Admin dan Manager yang boleh membuat kategori baru.
     */
    public function create(User $user): bool
    {
        return $user->is_active && ($user->isAdmin() || $user->isManager());
    }

    /**
     * Hanya Admin dan Manager yang boleh mengupdate kategori.
     */
    public function update(User $user, Category $category): bool
    {
        return $user->is_active && ($user->isAdmin() || $user->isManager());
    }

    /**
     * Hanya Admin yang boleh menghapus kategori.
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Category $category): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Category $category): bool
    {
        return false;
    }
}
