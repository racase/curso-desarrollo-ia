<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class DeleteUser
{
    public function handle(User $actor, User $user): void
    {
        try {
            app(ManageUsers::class)->transaction($actor, function () use ($actor, $user): void {
                $current = User::query()->lockForUpdate()->findOrFail($user->id);
                if ($current->id === $actor->id) {
                    throw ValidationException::withMessages(['user' => 'No puedes eliminar tu propia cuenta.']);
                }
                if ($current->hasRole(UserRole::Admin) && User::where('role', UserRole::Admin)->count() === 1) {
                    throw ValidationException::withMessages(['user' => 'Debe permanecer al menos un administrador.']);
                }
                if ($current->requestedIncidents()->exists() || $current->assignedIncidents()->exists()
                    || $current->incidentComments()->exists() || $current->incidentStatusHistory()->exists()) {
                    throw ValidationException::withMessages(['user' => 'No se puede eliminar una cuenta referenciada por incidencias, comentarios o historial.']);
                }
                $current->delete();
            });
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23503', '23000'], true)) {
                throw ValidationException::withMessages(['user' => 'La cuenta tiene referencias y no se puede eliminar. Actualiza el listado.']);
            }
            throw $exception;
        }
    }
}
