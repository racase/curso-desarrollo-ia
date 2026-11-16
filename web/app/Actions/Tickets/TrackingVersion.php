<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use Illuminate\Validation\ValidationException;

class TrackingVersion
{
    public static function check(Ticket $ticket, int $expectedVersion): void
    {
        if ($ticket->tracking_version !== $expectedVersion) {
            throw ValidationException::withMessages(['tracking_version' => 'La incidencia ha cambiado. Actualiza la ficha antes de continuar.']);
        }
    }
}
