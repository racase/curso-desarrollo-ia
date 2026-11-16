<?php

namespace App\Actions\Tickets;

use App\Actions\Users\UserAdministration;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AssignTicket
{
    public function handle(User $actor, Ticket $ticket, ?int $technicianId, int $expectedVersion): Ticket
    {
        return DB::transaction(function () use ($actor, $ticket, $technicianId, $expectedVersion): Ticket {
            $actor = UserAdministration::lock($actor);
            $current = Ticket::query()->lockForUpdate()->findOrFail($ticket->id);
            Gate::forUser($actor)->authorize('assign', $current);
            TrackingVersion::check($current, $expectedVersion);
            $canRemove = in_array($current->status, [TicketStatus::Proposed, TicketStatus::Open], true);
            Validator::make(['technician_id' => $technicianId], [
                'technician_id' => [$canRemove ? 'nullable' : 'required', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Technician->value)],
            ], [], ['technician_id' => 'técnico'])->validate();
            $current->technician_id = $technicianId;
            $current->tracking_version++;
            $current->save();

            return $current;
        });
    }
}
