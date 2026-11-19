<?php

namespace App\Actions\Tickets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Tickets\TicketClassifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateTicket
{
    public function __construct(private TicketClassifier $classifier) {}

    public function handle(User $actor, array $data): Ticket
    {
        Gate::forUser($actor)->authorize('create', Ticket::class);
        $validated = TicketData::validate($data);
        $classification = $this->classifier->classify($validated['description']);

        return DB::transaction(function () use ($actor, $validated, $classification): Ticket {
            Gate::forUser($actor)->authorize('create', Ticket::class);
            TicketData::lockAsset($validated);
            $ticket = new Ticket($validated + ($classification?->attributes() ?? ['category' => null, 'priority' => null]));
            $ticket->requester()->associate($actor);
            $ticket->status = TicketStatus::Proposed;
            $ticket->save();

            return $ticket->refresh();
        });
    }
}
