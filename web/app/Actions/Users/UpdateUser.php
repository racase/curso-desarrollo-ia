<?php

namespace App\Actions\Users;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateUser
{
    public function handle(User $actor, User $user, array $data): User
    {
        return DB::transaction(function () use ($actor, $user, $data): User {
            $actor = UserAdministration::lock($actor);
            $current = User::lockForUpdate()->findOrFail($user->id);
            Gate::forUser($actor)->authorize('update', $current);
            $validated = UserData::validate($data, $current);
            $role = $validated['role'] instanceof UserRole ? $validated['role'] : UserRole::from($validated['role']);
            if ($role !== $current->role) {
                self::ensureAdministratorRemains($current);
                if ($current->hasRole(UserRole::Technician) && Ticket::where('technician_id', $current->id)->where('status', '!=', TicketStatus::Closed)->exists()) {
                    throw ValidationException::withMessages(['role' => 'Reasigna o cierra las incidencias pendientes antes de cambiar el rol del técnico.']);
                }
            }
            $current->update($validated);

            return $current;
        });
    }

    public static function ensureAdministratorRemains(User $user): void
    {
        if ($user->hasRole(UserRole::Admin) && ! User::where('role', UserRole::Admin)->whereKeyNot($user->id)->exists()) {
            throw ValidationException::withMessages(['role' => 'Debe permanecer al menos un administrador.']);
        }
    }
}
