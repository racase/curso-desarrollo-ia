<?php

namespace App\Actions\Incidents;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateIncident
{
    public function handle(User $actor, array $input): Incident
    {
        Gate::forUser($actor)->authorize('create', Incident::class);
        $data = Validator::make([
            'title' => is_string($input['title'] ?? null) ? trim($input['title']) : ($input['title'] ?? null),
            'description' => is_string($input['description'] ?? null) ? trim($input['description']) : ($input['description'] ?? null),
            'priority' => $input['priority'] ?? IncidentPriority::Medium->value,
            'asset_id' => $input['asset_id'] ?? null,
        ], [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'priority' => ['required', Rule::enum(IncidentPriority::class)],
            'asset_id' => ['required', 'integer', Rule::exists('assets', 'id')],
        ])->validate();

        return Incident::create([
            ...$data,
            'requester_id' => $actor->id,
            'status' => IncidentStatus::Proposed,
            'technician_id' => null,
        ]);
    }
}
