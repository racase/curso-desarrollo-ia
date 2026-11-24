<?php

namespace App\Actions\Incidents;

use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AssignIncident
{
    public function handle(User $actor, Incident $incident, int $technicianId): Incident
    {
        Gate::forUser($actor)->authorize('assign', $incident);

        return DB::transaction(function () use ($actor, $incident, $technicianId): Incident {
            $current = Incident::query()->lockForUpdate()->findOrFail($incident->id);
            if ($actor->hasRole(UserRole::Technician) && $current->technician_id !== null) {
                throw ValidationException::withMessages(['technician_id' => 'La incidencia ya está asignada. Actualiza la ficha.']);
            }
            Gate::forUser($actor)->authorize('assign', $current);
            abort_if($actor->hasRole(UserRole::Technician) && $technicianId !== $actor->id, 403);
            $technician = User::query()->lockForUpdate()->find($technicianId);
            if (! $technician?->hasRole(UserRole::Technician)) {
                throw ValidationException::withMessages(['technician_id' => 'Selecciona un usuario con rol técnico.']);
            }
            $current->update(['technician_id' => $technician->id]);

            return $current;
        }, 3);
    }
}
