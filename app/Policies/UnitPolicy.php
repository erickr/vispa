<?php

namespace App\Policies;

use App\Models\Unit;
use App\Models\User;

/**
 * Units are a global catalog every recipe leans on, so anyone may browse them
 * but only the units admin may change them.
 */
class UnitPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Unit $unit): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isCatalogAdmin();
    }

    public function update(User $user, Unit $unit): bool
    {
        return $user->isCatalogAdmin();
    }

    public function delete(User $user, Unit $unit): bool
    {
        return $user->isCatalogAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isCatalogAdmin();
    }
}
