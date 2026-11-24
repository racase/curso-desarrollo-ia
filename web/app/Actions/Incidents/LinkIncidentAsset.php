<?php

namespace App\Actions\Incidents;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LinkIncidentAsset
{
    public function handle(User $actor, Incident $incident, array $input): Incident
    {
        return DB::transaction(function () use ($actor, $incident, $input): Incident {
            $currentActor = User::query()->findOrFail($actor->id);
            Gate::forUser($currentActor)->authorize('linkAsset', $incident);
            $data = Validator::make(['asset_id' => $input['asset_id'] ?? null], [
                'asset_id' => ['required', 'integer', Rule::exists('assets', 'id')],
            ])->validate();
            $current = Incident::query()->lockForUpdate()->findOrFail($incident->id);
            $current->update($data);

            return $current;
        }, 3);
    }
}
