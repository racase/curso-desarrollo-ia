<?php

namespace App\Services\Tickets;

interface TicketClassifier
{
    public function classify(string $description): ?TicketClassification;
}
