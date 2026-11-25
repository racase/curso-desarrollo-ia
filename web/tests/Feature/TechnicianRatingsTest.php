<?php

namespace Tests\Feature;

use App\Actions\Incidents\RateIncident;
use App\Enums\UserRole;
use App\Filament\Widgets\TechnicianRatings;
use App\Models\Incident;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TechnicianRatingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_averages_use_historical_technicians_and_exclude_unrated_incidents(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $first = User::factory()->technician()->create(['name' => 'Ana']);
        $second = User::factory()->technician()->create(['name' => 'Bea']);
        $empty = User::factory()->technician()->create(['name' => 'Cris']);
        foreach ([5, 4] as $score) {
            Incident::factory()->rated($score)->create(['technician_id' => $second->id, 'closing_technician_id' => $first->id]);
        }
        Incident::factory()->closed()->create(['technician_id' => $first->id, 'closing_technician_id' => $first->id]);
        Incident::factory()->rated(2)->create(['technician_id' => $second->id, 'closing_technician_id' => $second->id]);
        $first->update(['role' => UserRole::Requester]);
        $widget = Livewire::test(TechnicianRatings::class)->assertCanSeeTableRecords([$first, $second, $empty], inOrder: true)
            ->assertSee('4,5 / 5')->assertSee('2,0 / 5')->assertSee('Sin valoraciones')->assertSee('Histórico')
            ->assertSee('wire:poll.30s.visible', false);
        $rows = $widget->instance()->ratingsQuery()->get()->keyBy('id');
        $this->assertSame(2, (int) $rows[$first->id]->rating_count);
        $this->assertSame(4.5, (float) $rows[$first->id]->rating_average);
        $this->assertSame(0, (int) $rows[$empty->id]->rating_count);
        $this->get('/admin?tableFilters[status][value]=open')->assertOk()->assertSee('Valoraciones por técnico');
    }

    public function test_empty_state_registration_and_pagination(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(TechnicianRatings::class)->assertSee('No hay técnicos');
        $users = collect(range(1, 6))->map(fn ($index) => User::factory()->technician()->create(['name' => 'Técnico '.$index]));
        Livewire::test(TechnicianRatings::class)->assertCanSeeTableRecords($users->take(5), inOrder: true)
            ->assertCanNotSeeTableRecords($users->slice(5))->call('gotoPage', 2)
            ->assertCanSeeTableRecords($users->slice(5));
        $this->assertSame(1, count(array_filter(Filament::getPanel('admin')->getWidgets(), fn ($class) => $class === TechnicianRatings::class)));
    }

    public function test_updates_include_confirmed_scores_and_access_is_rechecked(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = Incident::factory()->closed()->create();
        $this->actingAs($admin);
        $widget = Livewire::test(TechnicianRatings::class)->assertSee('Sin valoraciones');
        app(RateIncident::class)->handle($incident->requester, $incident, ['resolution_rating' => 3]);
        $widget->call('$refresh')->assertSee('3,0 / 5');
        User::whereKey($admin->id)->update(['role' => UserRole::Requester]);
        $this->actingAs($admin->fresh());
        $widget->call('$refresh')->assertForbidden();
    }

    public function test_other_roles_and_anonymous_cannot_mount_or_query_the_widget(): void
    {
        Livewire::test(TechnicianRatings::class)->assertForbidden();
        foreach ([User::factory()->create(), User::factory()->technician()->create()] as $actor) {
            $this->actingAs($actor);
            $this->assertFalse(TechnicianRatings::canView());
            Livewire::test(TechnicianRatings::class)->assertForbidden();
        }
    }
}
