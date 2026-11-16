<?php

namespace App\Actions\Users;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketStatusChange;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteUser
{
    public function handle(User $actor, User $user): bool
    {
        try {
            return DB::transaction(function () use ($actor, $user): bool {
                $actor = UserAdministration::lock($actor);
                $current = User::lockForUpdate()->findOrFail($user->id);
                Gate::forUser($actor)->authorize('delete', $current);
                UpdateUser::ensureAdministratorRemains($current);
                if (Ticket::where('requester_id', $current->id)->orWhere('technician_id', $current->id)->exists()
                    || TicketComment::where('author_id', $current->id)->exists()
                    || TicketStatusChange::where('author_id', $current->id)->exists()) {
                    throw ValidationException::withMessages(['user' => 'No se puede eliminar un usuario con incidencias, comentarios o historial relacionados.']);
                }

                return $current->delete();
            });
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['user' => 'El usuario tiene información relacionada y no puede eliminarse.']);
        }
    }
}
