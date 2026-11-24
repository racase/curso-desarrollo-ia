<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\User;

class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Incident $incident): bool
    {
        return ! $user->hasRole(UserRole::Requester) || $incident->requester_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function assign(User $user, Incident $incident): bool
    {
        return $user->hasRole(UserRole::Admin)
            || ($user->hasRole(UserRole::Technician) && $incident->technician_id === null);
    }

    public function transition(User $user, Incident $incident): bool
    {
        return $user->hasRole(UserRole::Admin)
            || ($user->hasRole(UserRole::Technician) && $incident->technician_id === $user->id);
    }

    public function update(User $user, Incident $incident): bool
    {
        return false;
    }

    public function linkAsset(User $user, Incident $incident): bool
    {
        return $user->hasRole(UserRole::Admin);
    }

    public function delete(User $user, Incident $incident): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
