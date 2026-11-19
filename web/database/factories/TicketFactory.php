<?php

namespace Database\Factories;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    public function classified(): static
    {
        return $this->state(['category' => TicketCategory::Hardware]);
    }

    public function unclassified(): static
    {
        return $this->state(['category' => null, 'priority' => null]);
    }

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'priority' => TicketPriority::Medium,
            'status' => TicketStatus::Proposed,
            'requester_id' => User::factory(),
            'technician_id' => null,
            'tracking_version' => 0,
        ];
    }
}
