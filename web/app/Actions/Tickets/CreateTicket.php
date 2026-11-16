<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateTicket
{
    public function handle(User $actor, array $data): Ticket
    {
        return DB::transaction(function () use ($actor, $data): Ticket {
            Gate::forUser($actor)->authorize('create', Ticket::class);
            $ticket = new Ticket(TicketData::validate($data));
            $ticket->requester()->associate($actor);
            $ticket->status = TicketStatus::Proposed;
            $ticket->save();

            return $ticket->refresh();
        });
    }
}
