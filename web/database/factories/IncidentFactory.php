<?php

namespace Database\Factories;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Models\Asset;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Incident> */
class IncidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => IncidentStatus::Proposed,
            'priority' => IncidentPriority::Medium,
            'requester_id' => User::factory(),
            'technician_id' => null,
            'asset_id' => Asset::factory(),
        ];
    }

    public function assigned(): static
    {
        return $this->state(fn () => ['technician_id' => User::factory()->technician()]);
    }

    public function withoutAsset(): static
    {
        return $this->state(fn () => ['asset_id' => null]);
    }
}
