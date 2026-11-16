<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TransitionTicket
{
    public function handle(User $actor, Ticket $ticket, TicketStatus $target, int $expectedVersion): Ticket
    {
        return DB::transaction(function () use ($actor, $ticket, $target, $expectedVersion): Ticket {
            $current = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            Gate::forUser($actor)->authorize('transition', $current);
            TrackingVersion::check($current, $expectedVersion);
            if ($current->status->next() !== $target) {
                throw ValidationException::withMessages(['status' => 'La transición de estado no es válida.']);
            }
            if ($target === TicketStatus::InProgress && $current->technician_id === null) {
                throw ValidationException::withMessages(['status' => 'Asigna un técnico antes de pasar a en curso.']);
            }
            $previous = $current->status;
            $current->status = $target;
            $current->tracking_version++;
            $current->save();
            $current->statusChanges()->create(['from_status' => $previous, 'to_status' => $target, 'author_id' => $actor->id]);

            return $current;
        });
    }
}
