<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketStatusChangeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'author_id' => User::factory()->admin(),
            'from_status' => TicketStatus::Proposed,
            'to_status' => TicketStatus::Open,
        ];
    }
}
