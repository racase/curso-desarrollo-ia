<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class UpdateUser
{
    public function handle(User $actor, User $user, array $input): User
    {
        $manager = app(ManageUsers::class);

        return $manager->transaction($actor, function () use ($manager, $user, $input): User {
            $current = User::query()->lockForUpdate()->findOrFail($user->id);
            $data = $manager->validate($input, $current);
            $role = $data['role'] instanceof UserRole ? $data['role'] : UserRole::from($data['role']);
            if ($current->hasRole(UserRole::Admin) && $role !== UserRole::Admin && User::where('role', UserRole::Admin)->count() === 1) {
                throw ValidationException::withMessages(['role' => 'Debe permanecer al menos un administrador.']);
            }
            if ($current->hasRole(UserRole::Technician) && $role !== UserRole::Technician && $current->assignedIncidents()->exists()) {
                throw ValidationException::withMessages(['role' => 'Reasigna todas las incidencias del técnico antes de cambiar su rol, incluidas las cerradas.']);
            }
            $current->update($data);

            return $current;
        });
    }
}
