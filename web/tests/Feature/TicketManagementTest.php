<?php

namespace Tests\Feature;

use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\UpdateTicket;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TicketManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_creation_uses_session_identity_and_initial_values(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $ticket = app(CreateTicket::class)->handle($actor, [
            'title' => '  Fallo de impresora  ', 'description' => '  No imprime ningún documento.  ', 'priority' => 'medium',
            'requester_id' => $other->id, 'technician_id' => $other->id, 'status' => 'closed', 'created_at' => '2000-01-01',
        ]);

        $this->assertSame('Fallo de impresora', $ticket->title);
        $this->assertSame('No imprime ningún documento.', $ticket->description);
        $this->assertSame($actor->id, $ticket->requester->id);
        $this->assertSame(TicketStatus::Proposed, $ticket->status);
        $this->assertSame(TicketPriority::Medium, $ticket->priority);
        $this->assertNull($ticket->technician);
        $this->assertSame(0, $ticket->tracking_version);
        $this->assertTrue($ticket->created_at->isToday());
    }

    public function test_creation_rejects_invalid_fields_and_boundaries(): void
    {
        $actor = User::factory()->create();
        foreach ([['title' => '  '], ['title' => 'ab'], ['title' => str_repeat('a', 201)], ['description' => 'short'], ['description' => str_repeat('x', 10001)], ['priority' => 'urgent'], ['priority' => null]] as $invalid) {
            try {
                app(CreateTicket::class)->handle($actor, array_replace(['title' => 'Valid title', 'description' => 'Valid description.', 'priority' => 'low'], $invalid));
                $this->fail('Invalid ticket was accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey(array_key_first($invalid), $exception->errors());
            }
        }
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_visibility_and_policy_keep_requesters_isolated(): void
    {
        $owner = User::factory()->create();
        $own = Ticket::factory()->create(['requester_id' => $owner->id]);
        $other = Ticket::factory()->create();

        $this->assertSame([$own->id], Ticket::visibleTo($owner)->pluck('id')->all());
        $this->assertFalse(Gate::forUser($owner)->allows('view', $other));
        $this->assertFalse(Gate::forUser($owner)->allows('update', $other));
        foreach ([User::factory()->technician()->create(), User::factory()->admin()->create()] as $staff) {
            $this->assertSame(2, Ticket::visibleTo($staff)->count());
            $this->assertTrue(Gate::forUser($staff)->allows('view', $other));
        }
    }

    public function test_edit_permissions_for_every_state_and_role(): void
    {
        $owner = User::factory()->create();
        $technician = User::factory()->technician()->create();
        $admin = User::factory()->admin()->create();
        foreach (TicketStatus::cases() as $status) {
            $ticket = Ticket::factory()->create(['requester_id' => $owner->id, 'status' => $status]);
            $this->assertSame(in_array($status, [TicketStatus::Proposed, TicketStatus::Open], true), Gate::forUser($owner)->allows('update', $ticket));
            foreach ([$technician, $admin] as $staff) {
                $this->assertSame($status !== TicketStatus::Closed, Gate::forUser($staff)->allows('update', $ticket));
            }
            $this->assertFalse(Gate::forUser($admin)->allows('delete', $ticket));
        }
    }

    public function test_update_cannot_change_protected_attributes(): void
    {
        $ticket = Ticket::factory()->create();
        $updated = app(UpdateTicket::class)->handle($ticket->requester, $ticket, [
            'title' => 'Corrección de título', 'description' => 'Descripción corregida.', 'priority' => 'critical',
            'status' => 'closed', 'requester_id' => 999, 'technician_id' => 999, 'created_at' => '2000-01-01',
        ]);
        $this->assertSame(TicketStatus::Proposed, $updated->status);
        $this->assertSame($ticket->requester_id, $updated->requester_id);
        $this->assertTrue($ticket->created_at->equalTo($updated->created_at));
        $this->assertNull($updated->technician_id);
        $this->assertSame(TicketPriority::Critical, $updated->priority);
    }

    public function test_update_reloads_the_current_state_before_authorizing(): void
    {
        $ticket = Ticket::factory()->create();
        Ticket::whereKey($ticket->id)->update(['status' => TicketStatus::Closed]);
        $this->expectException(AuthorizationException::class);
        app(UpdateTicket::class)->handle($ticket->requester, $ticket, ['title' => 'Changed', 'description' => 'A valid description', 'priority' => 'high']);
    }

    public function test_new_migration_can_be_reversed_and_reapplied(): void
    {
        $migration = require database_path('migrations/2026_11_16_000001_create_tickets_table.php');
        $migration->down();
        $migration->up();
        $this->assertNotNull(Ticket::factory()->create()->requester);
    }
}
