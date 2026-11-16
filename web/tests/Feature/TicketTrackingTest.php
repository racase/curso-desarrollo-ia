<?php

namespace Tests\Feature;

use App\Actions\Tickets\AssignTicket;
use App\Actions\Tickets\TransitionTicket;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\TicketStatusChange;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TicketTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_and_reassignment_preserve_state_without_history(): void
    {
        $ticket = Ticket::factory()->create();
        $actor = User::factory()->technician()->create();
        $other = User::factory()->technician()->create();
        $assigned = app(AssignTicket::class)->handle($actor, $ticket, $actor->id, 0);
        $assigned = app(AssignTicket::class)->handle($actor, $assigned, $other->id, 1);
        $this->assertSame($other->id, $assigned->technician->id);
        $this->assertSame(TicketStatus::Proposed, $assigned->status);
        $this->assertSame(2, $assigned->tracking_version);
        $this->assertSame(0, $assigned->statusChanges()->count());
    }

    public function test_assignment_rejects_non_technicians_and_invalid_removal(): void
    {
        $actor = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create(['status' => 'in_progress', 'technician_id' => User::factory()->technician()]);
        foreach ([$actor->id, User::factory()->create()->id, 99999, null] as $invalid) {
            try {
                app(AssignTicket::class)->handle($actor, $ticket, $invalid, 0);
                $this->fail('Invalid assignment accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('technician_id', $exception->errors());
            }
        }
        $this->assertSame(0, $ticket->refresh()->tracking_version);
    }

    public function test_assignment_requires_staff_and_an_unclosed_ticket(): void
    {
        $ticket = Ticket::factory()->create();
        $technician = User::factory()->technician()->create();
        foreach ([$ticket->requester, User::factory()->admin()->create()] as $actor) {
            if ($actor->hasRole(UserRole::Admin)) {
                $ticket->status = TicketStatus::Closed;
                $ticket->save();
            }
            try {
                app(AssignTicket::class)->handle($actor, $ticket, $technician->id, 0);
                $this->fail('Unauthorized assignment accepted');
            } catch (AuthorizationException) {
                $this->assertNull($ticket->refresh()->technician_id);
            }
        }
    }

    public function test_every_pair_of_states_obeys_the_linear_flow(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (TicketStatus::cases() as $from) {
            foreach (TicketStatus::cases() as $to) {
                $ticket = Ticket::factory()->create(['status' => $from, 'technician_id' => User::factory()->technician()]);
                try {
                    $result = app(TransitionTicket::class)->handle($admin, $ticket, $to, 0);
                    $this->assertSame($from->next(), $to);
                    $this->assertSame($to, $result->status);
                    $change = $result->statusChanges()->sole();
                    $this->assertSame($from, $change->from_status);
                    $this->assertSame($to, $change->to_status);
                    $this->assertSame($admin->id, $change->author_id);
                } catch (AuthorizationException|ValidationException) {
                    $this->assertNotSame($from->next(), $to);
                    $this->assertSame($from, $ticket->refresh()->status);
                    $this->assertSame(0, $ticket->statusChanges()->count());
                }
            }
        }
    }

    public function test_admin_opens_without_a_technician_but_cannot_start_attention(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = app(TransitionTicket::class)->handle($admin, Ticket::factory()->create(), TicketStatus::Open, 0);
        $this->assertSame(TicketStatus::Open, $ticket->status);
        $this->expectException(ValidationException::class);
        app(TransitionTicket::class)->handle($admin, $ticket, TicketStatus::InProgress, 1);
    }

    public function test_only_assigned_technician_or_admin_can_transition(): void
    {
        $ticket = Ticket::factory()->create(['technician_id' => User::factory()->technician()]);
        foreach ([$ticket->requester, User::factory()->technician()->create()] as $actor) {
            try {
                app(TransitionTicket::class)->handle($actor, $ticket, TicketStatus::Open, 0);
                $this->fail('Unauthorized transition accepted');
            } catch (AuthorizationException) {
                $this->assertSame(TicketStatus::Proposed, $ticket->refresh()->status);
            }
        }
        $result = app(TransitionTicket::class)->handle($ticket->technician, $ticket, TicketStatus::Open, 0);
        $this->assertSame(TicketStatus::Open, $result->status);
    }

    public function test_stale_version_is_rejected_after_reassignment(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();
        app(AssignTicket::class)->handle($admin, $ticket, User::factory()->technician()->create()->id, 0);
        $this->expectException(ValidationException::class);
        app(TransitionTicket::class)->handle($admin, $ticket, TicketStatus::Open, 0);
    }

    public function test_history_failure_rolls_back_state_and_version(): void
    {
        $ticket = Ticket::factory()->create();
        TicketStatusChange::creating(function (): void {
            throw new \RuntimeException('Simulated history failure');
        });
        try {
            app(TransitionTicket::class)->handle(User::factory()->admin()->create(), $ticket, TicketStatus::Open, 0);
            $this->fail('Expected history failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated history failure', $exception->getMessage());
            $this->assertSame(TicketStatus::Proposed, $ticket->refresh()->status);
            $this->assertSame(0, $ticket->tracking_version);
            $this->assertDatabaseCount('ticket_status_changes', 0);
        } finally {
            TicketStatusChange::flushEventListeners();
        }
    }

    public function test_history_is_stably_ordered_and_protects_author_references(): void
    {
        $ticket = Ticket::factory()->create();
        $author = User::factory()->admin()->create();
        $first = TicketStatusChange::factory()->create(['ticket_id' => $ticket->id, 'author_id' => $author->id, 'created_at' => now()]);
        $second = TicketStatusChange::factory()->create(['ticket_id' => $ticket->id, 'created_at' => $first->created_at]);
        $this->assertSame([$first->id, $second->id], $ticket->statusChanges()->pluck('id')->all());
        $this->expectException(QueryException::class);
        $author->delete();
    }
}
