<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class UpdateTicket
{
    public function handle(User $actor, Ticket $ticket, array $data): Ticket
    {
        return DB::transaction(function () use ($actor, $ticket, $data): Ticket {
            $current = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            Gate::forUser($actor)->authorize('update', $current);
            $validated = TicketData::validate($data);
            if (TicketData::classificationChanges($current, $data)) {
                Gate::forUser($actor)->authorize('classify', $current);
                $validated += TicketData::classification($data);
            }
            TicketData::lockAsset($validated);
            $current->update($validated);

            return $current;
        });
    }
}
