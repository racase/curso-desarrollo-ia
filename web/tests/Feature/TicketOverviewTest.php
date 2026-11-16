<?php

namespace Tests\Feature;

use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\TransitionTicket;
use App\Enums\TicketStatus;
use App\Filament\Widgets\TicketStatusOverview;
use App\Models\Ticket;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_respect_roles_and_always_include_four_states(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $owner = User::factory()->create();
        foreach (TicketStatus::cases() as $state) {
            Ticket::factory()->create(['requester_id' => $owner->id, 'status' => $state]);
            Ticket::factory()->create(['status' => $state]);
        }
        foreach ([[$owner, 1], [User::factory()->technician()->create(), 2], [User::factory()->admin()->create(), 2], [User::factory()->create(), 0]] as [$actor, $count]) {
            $this->actingAs($actor);
            $this->assertSame(['proposed' => $count, 'open' => $count, 'in_progress' => $count, 'resolved' => $count], app(TicketStatusOverview::class)->counts());
            Livewire::test(TicketStatusOverview::class)->assertSee('Propuesta')->assertSee('Abierta')->assertSee('En curso')->assertSee('Resuelta')->assertDontSee('Cerrada');
            $this->get('/admin')->assertOk()->assertSeeLivewire(TicketStatusOverview::class);
        }
    }

    public function test_refresh_recalculates_after_creation_transition_and_close(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($owner);
        $widget = Livewire::test(TicketStatusOverview::class);
        $this->assertSame(0, $widget->instance()->counts()['proposed']);
        $ticket = app(CreateTicket::class)->handle($owner, ['title' => 'Nuevo caso', 'description' => 'Descripción del problema.', 'priority' => 'medium']);
        $widget->call('$refresh');
        $this->assertSame(1, $widget->instance()->counts()['proposed']);
        app(TransitionTicket::class)->handle($admin, $ticket, TicketStatus::Open, 0);
        $widget->call('$refresh');
        $this->assertSame(['proposed' => 0, 'open' => 1, 'in_progress' => 0, 'resolved' => 0], $widget->instance()->counts());
        $resolved = Ticket::factory()->create(['requester_id' => $owner->id, 'status' => 'resolved']);
        app(TransitionTicket::class)->handle($admin, $resolved, TicketStatus::Closed, 0);
        $widget->call('$refresh');
        $this->assertSame(0, $widget->instance()->counts()['resolved']);
        $this->actingAs(User::factory()->create());
        $widget->call('$refresh');
        $this->assertSame(['proposed' => 0, 'open' => 0, 'in_progress' => 0, 'resolved' => 0], $widget->instance()->counts());
    }

    public function test_guests_cannot_request_counts(): void
    {
        $this->assertFalse(TicketStatusOverview::canView());
        $this->get('/admin')->assertRedirect('/admin/login');
        Livewire::test(TicketStatusOverview::class)->assertForbidden();
    }
}
