<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class AddTicketComment
{
    public function handle(User $actor, Ticket $ticket, array $data): TicketComment
    {
        return DB::transaction(function () use ($actor, $ticket, $data): TicketComment {
            $current = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            Gate::forUser($actor)->authorize('comment', $current);
            if (is_string($data['body'] ?? null)) {
                $data['body'] = trim($data['body']);
            }
            $validated = Validator::make($data, ['body' => ['required', 'string', 'min:1', 'max:5000']], [], ['body' => 'comentario'])->validate();

            return $current->comments()->create(['body' => $validated['body'], 'author_id' => $actor->id]);
        });
    }
}
