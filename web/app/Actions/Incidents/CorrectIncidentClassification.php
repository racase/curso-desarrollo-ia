<?php

namespace App\Actions\Incidents;

use App\Enums\IncidentCategory;
use App\Enums\IncidentPriority;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CorrectIncidentClassification
{
    public function handle(User $actor, Incident $incident, array $input): Incident
    {
        Gate::forUser(User::query()->findOrFail($actor->id))->authorize('classify', $incident);
        $data = Validator::make([
            'category' => $input['category'] ?? null,
            'priority' => $input['priority'] ?? null,
        ], [
            'category' => ['required', Rule::enum(IncidentCategory::class)],
            'priority' => ['required', Rule::enum(IncidentPriority::class)],
        ])->validate();

        // Update only these fields, even when the caller holds a stale record.
        Incident::query()->whereKey($incident->id)->update($data);

        return $incident->refresh();
    }
}
