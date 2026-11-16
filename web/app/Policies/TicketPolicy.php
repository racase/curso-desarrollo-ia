<?php

namespace App\Policies;

use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return ! $user->hasRole(UserRole::Requester) || $ticket->requester_id === $user->id;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket)
            && $ticket->status !== TicketStatus::Closed
            && (! $user->hasRole(UserRole::Requester) || in_array($ticket->status, [TicketStatus::Proposed, TicketStatus::Open], true));
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        return ! $user->hasRole(UserRole::Requester) && $ticket->status !== TicketStatus::Closed;
    }

    public function transition(User $user, Ticket $ticket): bool
    {
        return $ticket->status !== TicketStatus::Closed
            && ($user->hasRole(UserRole::Admin)
                || ($user->hasRole(UserRole::Technician) && $ticket->technician_id === $user->id));
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket) && $ticket->status !== TicketStatus::Closed;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
