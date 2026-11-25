<?php

namespace App\Actions\Incidents;

use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RateIncident
{
    public function handle(User $actor, Incident $incident, array $input): Incident
    {
        return DB::transaction(function () use ($actor, $incident, $input): Incident {
            $current = Incident::query()->lockForUpdate()->findOrFail($incident->id);
            $actor->refresh();
            // Only the owner may learn that a previously opened form is now obsolete.
            if ($actor->hasRole(UserRole::Requester) && $current->requester_id === $actor->id && $current->resolution_rating !== null) {
                throw ValidationException::withMessages(['resolution_rating' => 'Esta incidencia ya está valorada. Actualiza la ficha.']);
            }
            Gate::forUser($actor)->authorize('rate', $current);
            $data = Validator::make($input, ['resolution_rating' => ['required', 'integer', 'between:1,5',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_int($value) && ! is_string($value)) {
                        $fail('Selecciona un número entero de 1 a 5 estrellas.');
                    }
                },
            ]])->validate();
            $current->resolution_rating = (int) $data['resolution_rating'];
            $current->rated_at = now();
            $current->save();

            return $current;
        }, 3);
    }
}
