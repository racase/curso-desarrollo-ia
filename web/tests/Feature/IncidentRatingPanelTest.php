<?php

namespace Tests\Feature;

use App\Actions\Incidents\RateIncident;
use App\Filament\Resources\Incidents\Pages\ViewIncident;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IncidentRatingPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_choose_a_score_and_read_it_after_reload(): void
    {
        $incident = Incident::factory()->closed()->create();
        $this->actingAs($incident->requester);
        Livewire::test(ViewIncident::class, ['record' => $incident->id])
            ->assertActionVisible('rate')->mountAction('rate')
            ->assertSchemaStateSet(['resolution_rating' => null])
            ->callMountedAction()->assertHasActionErrors(['resolution_rating' => 'required']);
        Livewire::test(ViewIncident::class, ['record' => $incident->id])
            ->callAction('rate', ['resolution_rating' => 4])->assertHasNoActionErrors()
            ->assertActionHidden('rate')->assertSee('Tu valoración de la atención')->assertSee('4 / 5');
        Livewire::test(ViewIncident::class, ['record' => $incident->id])
            ->assertActionHidden('rate')->assertSee('Fecha de tu valoración')->assertSee('4 / 5');
    }

    public function test_staff_cannot_mount_action_or_read_individual_scores(): void
    {
        $incident = Incident::factory()->rated()->create();
        foreach ([User::factory()->admin()->create(), $incident->technician] as $actor) {
            $this->actingAs($actor);
            Livewire::test(ViewIncident::class, ['record' => $incident->id])
                ->assertActionHidden('rate')->assertDontSee('Tu valoración de la atención')
                ->assertDontSee('Fecha de tu valoración')->call('mountAction', 'rate')->assertSet('mountedActions', []);
        }
        $this->actingAs(User::factory()->create());
        Livewire::test(ViewIncident::class, ['record' => $incident->id])->assertNotFound();
        auth()->logout();
        $this->get('/admin/incidents/'.$incident->id)->assertRedirect('/admin/login');
    }

    public function test_pending_and_unattributed_incidents_have_no_rating_action(): void
    {
        foreach ([Incident::factory()->create(), Incident::factory()->historicalWithoutTechnician()->create()] as $incident) {
            $this->actingAs($incident->requester);
            Livewire::test(ViewIncident::class, ['record' => $incident->id])
                ->assertActionHidden('rate')->call('mountAction', 'rate')->assertSet('mountedActions', []);
        }
    }

    public function test_stale_form_does_not_overwrite_a_confirmed_rating(): void
    {
        $incident = Incident::factory()->closed()->create();
        $this->actingAs($incident->requester);
        $page = Livewire::test(ViewIncident::class, ['record' => $incident->id])->mountAction('rate');
        app(RateIncident::class)->handle($incident->requester, $incident, ['resolution_rating' => 5]);
        $page->fillForm(['resolution_rating' => 1])->callMountedAction()->assertNotified();
        $this->assertSame(5, $incident->fresh()->resolution_rating);
    }
}
