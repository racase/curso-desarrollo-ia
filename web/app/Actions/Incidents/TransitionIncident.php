<?php

namespace App\Actions\Incidents;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TransitionIncident
{
    public function handle(User $actor, Incident $incident, IncidentStatus $expectedStatus, IncidentStatus $target): Incident
    {
        return DB::transaction(function () use ($actor, $incident, $expectedStatus, $target): Incident {
            $current = Incident::query()->lockForUpdate()->findOrFail($incident->id);
            Gate::forUser($actor)->authorize('transition', $current);
            if ($current->status !== $expectedStatus) {
                throw ValidationException::withMessages(['status' => 'El estado ha cambiado. Actualiza la ficha.']);
            }
            if ($current->status->next() !== $target) {
                throw ValidationException::withMessages(['status' => 'La transición de estado no está permitida.']);
            }
            if ($target === IncidentStatus::InProgress && $current->technician_id === null) {
                throw ValidationException::withMessages(['status' => 'Asigna un técnico antes de pasar a en curso.']);
            }
            $current->update(['status' => $target]);
            if ($target === IncidentStatus::Closed) {
                $current->closing_technician_id = $current->technician_id;
                $current->save();
            }
            $current->statusHistory()->create([
                'actor_id' => $actor->id,
                'from_status' => $expectedStatus,
                'to_status' => $target,
            ]);

            return $current;
        });
    }
}
