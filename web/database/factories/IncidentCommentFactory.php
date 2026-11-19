<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IncidentComment> */
class IncidentCommentFactory extends Factory
{
    public function definition(): array
    {
        return ['incident_id' => Incident::factory(), 'author_id' => User::factory(), 'body' => fake()->paragraph()];
    }
}
