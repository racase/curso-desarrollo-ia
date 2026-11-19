<?php

namespace App\Services\Tickets;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;

readonly class TicketClassification
{
    public function __construct(public TicketCategory $category, public TicketPriority $priority) {}

    public function attributes(): array
    {
        return ['category' => $this->category, 'priority' => $this->priority];
    }
}
