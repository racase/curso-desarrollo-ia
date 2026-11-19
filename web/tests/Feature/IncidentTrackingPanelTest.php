<?php

namespace Tests\Feature;

use App\Actions\Incidents\TransitionIncident;
use App\Enums\IncidentStatus;
use App\Filament\Resources\Incidents\Pages\ViewIncident;
use App\Filament\Resources\Incidents\RelationManagers\StatusHistoryRelationManager;
use App\Models\Incident;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IncidentTrackingPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_and_advance_and_history_is_visible(): void
    {
        $this->actingAs($admin = User::factory()->admin()->create());
        $incident = Incident::factory()->create();
        $technician = User::factory()->technician()->create();
        Livewire::test(ViewIncident::class, ['record' => $incident->id])
            ->callAction('assign', ['technician_id' => $technician->id])->assertHasNoActionErrors()
            ->callAction('transition')->assertHasNoActionErrors()->assertSee('Abierta');
        $this->assertSame($technician->id, $incident->fresh()->technician_id);
        Livewire::test(StatusHistoryRelationManager::class, ['ownerRecord' => $incident, 'pageClass' => ViewIncident::class])
            ->assertCanSeeTableRecords($incident->statusHistory)->assertSee($admin->name);
    }

    public function test_technician_self_assigns_and_requester_cannot_call_staff_actions(): void
    {
        $incident = Incident::factory()->create();
        $this->actingAs($technician = User::factory()->technician()->create());
        Livewire::test(ViewIncident::class, ['record' => $incident->id])->assertActionVisible('selfAssign')
            ->assertActionHidden('assign')->assertActionHidden('transition')->callAction('selfAssign');
        $this->assertSame($technician->id, $incident->fresh()->technician_id);
        Livewire::test(ViewIncident::class, ['record' => $incident->id])->assertActionVisible('transition')->assertActionHidden('selfAssign');
        $this->actingAs($incident->requester);
        Livewire::test(ViewIncident::class, ['record' => $incident->id])->assertActionHidden('assign')
            ->assertActionHidden('transition')->assertActionHidden('selfAssign')->call('mountAction', 'transition')->assertSet('mountedActions', []);
        $this->assertSame(IncidentStatus::Proposed, $incident->fresh()->status);
    }

    public function test_stale_confirmation_shows_a_conflict_without_duplicate_history(): void
    {
        $this->actingAs($admin = User::factory()->admin()->create());
        $incident = Incident::factory()->assigned()->create();
        $page = Livewire::test(ViewIncident::class, ['record' => $incident->id])->mountAction('transition');
        app(TransitionIncident::class)->handle($admin, $incident, IncidentStatus::Proposed, IncidentStatus::Open);
        $page->callMountedAction()->assertNotified(Notification::make()->title('No se pudo actualizar la incidencia')
            ->body('El estado ha cambiado. Actualiza la ficha.')->danger());
        $this->assertSame(1, $incident->statusHistory()->count());
        $this->assertSame(IncidentStatus::Open, $incident->fresh()->status);
    }

    public function test_requester_cannot_mount_history_of_another_incident(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(StatusHistoryRelationManager::class, ['ownerRecord' => Incident::factory()->create(), 'pageClass' => ViewIncident::class])->assertForbidden();
    }
}
