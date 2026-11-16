<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Asset $record): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function update(User $user, Asset $record): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, Asset $record): bool
    {
        return $this->create($user);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
