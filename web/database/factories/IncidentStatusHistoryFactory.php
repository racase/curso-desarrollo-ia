<?php

namespace Database\Factories;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IncidentStatusHistory> */
class IncidentStatusHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'actor_id' => User::factory()->admin(),
            'from_status' => IncidentStatus::Proposed,
            'to_status' => IncidentStatus::Open,
        ];
    }
}
