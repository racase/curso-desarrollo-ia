<?php

namespace Tests\Feature;

use App\Actions\Tickets\AssignTicket;
use App\Actions\Tickets\TransitionTicket;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Resources\Tickets\Pages\CreateTicket;
use App\Filament\Resources\Tickets\Pages\EditTicket;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Tickets\RelationManagers\StatusChangesRelationManager;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_guests_must_sign_in(): void
    {
        $this->get(TicketResource::getUrl('index'))->assertRedirect('/admin/login');
    }

    public function test_user_can_create_and_view_a_proposal(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(CreateTicket::class)
            ->assertFormSet(['priority' => TicketPriority::Medium])
            ->fillForm(['title' => 'Impresora averiada', 'description' => 'No imprime desde esta mañana.'])
            ->call('create')->assertHasNoFormErrors();
        $ticket = Ticket::sole();
        $this->assertSame(TicketStatus::Proposed, $ticket->status);
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])->assertOk()->assertSee('Impresora averiada');
    }

    public function test_list_filters_are_combined_with_visibility(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $proposal = Ticket::factory()->create(['requester_id' => $owner->id, 'priority' => 'high']);
        $open = Ticket::factory()->create(['requester_id' => $owner->id, 'status' => 'open']);
        $other = Ticket::factory()->create(['priority' => 'high']);
        Livewire::test(ListTickets::class)
            ->assertCanSeeTableRecords([$proposal, $open])->assertCanNotSeeTableRecords([$other])
            ->filterTable('status', 'proposed')->filterTable('priority', 'high')
            ->assertCanSeeTableRecords([$proposal])->assertCanNotSeeTableRecords([$open, $other])
            ->filterTable('priority', 'critical')->assertCountTableRecords(0);
    }

    public function test_requester_cannot_open_or_edit_other_users_ticket(): void
    {
        $this->actingAs(User::factory()->create());
        $ticket = Ticket::factory()->create();
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])->assertNotFound();
        Livewire::test(EditTicket::class, ['record' => $ticket->id])->assertNotFound();
    }

    public function test_basic_edit_saves_but_closed_ticket_is_protected(): void
    {
        $ticket = Ticket::factory()->create();
        $this->actingAs($ticket->requester);
        Livewire::test(EditTicket::class, ['record' => $ticket->id])
            ->fillForm(['title' => 'Título corregido'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Título corregido', $ticket->refresh()->title);
        $ticket->status = TicketStatus::Closed;
        $ticket->save();
        Livewire::test(EditTicket::class, ['record' => $ticket->id])->assertForbidden();
    }

    public function test_staff_can_consult_all_tickets(): void
    {
        $tickets = Ticket::factory()->count(3)->create();
        foreach ([User::factory()->technician()->create(), User::factory()->admin()->create()] as $actor) {
            $this->actingAs($actor);
            Livewire::test(ListTickets::class)->assertCanSeeTableRecords($tickets);
            Livewire::test(ViewTicket::class, ['record' => $tickets->first()->id])->assertOk();
        }
    }

    public function test_tracking_actions_and_history_are_available_to_authorized_users(): void
    {
        $ticket = Ticket::factory()->create();
        $admin = User::factory()->admin()->create();
        $technician = User::factory()->technician()->create();
        $this->actingAs($admin);
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->callAction('assign', ['technician_id' => $technician->id, 'tracking_version' => 0])
            ->assertHasNoActionErrors()
            ->callAction('transition', ['status' => 'open', 'tracking_version' => 1])
            ->assertHasNoActionErrors()->assertSee('Abierta')->assertDispatched('ticket-updated');
        $this->assertSame($technician->id, $ticket->refresh()->technician_id);
        $change = $ticket->statusChanges()->sole();
        Livewire::test(StatusChangesRelationManager::class, [
            'ownerRecord' => $ticket, 'pageClass' => ViewTicket::class,
        ])->assertCanSeeTableRecords([$change])->assertSee($admin->name);
        $this->actingAs($ticket->requester);
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->assertActionHidden('assign')->assertActionHidden('transition')->assertSee('Alta en propuesta');
    }

    public function test_stale_tracking_form_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();
        $this->actingAs($admin);
        $page = Livewire::test(ViewTicket::class, ['record' => $ticket->id])->mountAction('transition');
        app(AssignTicket::class)->handle($admin, $ticket, User::factory()->technician()->create()->id, 0);
        $page->callMountedAction()->assertHasActionErrors(['tracking_version']);
        $this->assertSame(TicketStatus::Proposed, $ticket->refresh()->status);
    }

    public function test_trimmed_validation_errors_are_attached_to_form_fields(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(CreateTicket::class)
            ->fillForm(['title' => '   ', 'description' => 'Texto suficientemente largo'])
            ->call('create')->assertHasFormErrors(['title']);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_history_refreshes_when_tracking_is_updated(): void
    {
        $admin = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();
        $this->actingAs($admin);
        $history = Livewire::test(StatusChangesRelationManager::class, ['ownerRecord' => $ticket, 'pageClass' => ViewTicket::class])
            ->assertCountTableRecords(0);
        app(TransitionTicket::class)->handle($admin, $ticket, TicketStatus::Open, 0);
        $history->dispatch('ticket-updated')->assertCountTableRecords(1)->assertSee('Abierta');
    }
}
