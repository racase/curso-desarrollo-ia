<?php

namespace Tests\Feature;

use App\Actions\Incidents\CreateIncident;
use App\Actions\Incidents\TransitionIncident;
use App\Enums\IncidentStatus;
use App\Filament\Widgets\IncidentStatusOverview;
use App\Models\Asset;
use App\Models\Incident;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\AccountWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IncidentOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_counts_follow_visibility_for_two_requesters_and_both_staff_roles(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        foreach (IncidentStatus::cases() as $index => $status) {
            Incident::factory()->withoutAsset()->count($index + 1)->create(['requester_id' => $first->id, 'status' => $status]);
            Incident::factory()->count(2)->create(['requester_id' => $second->id, 'status' => $status]);
        }
        foreach ([[$first, [1, 2, 3, 4]], [$second, [2, 2, 2, 2]],
            [User::factory()->admin()->create(), [3, 4, 5, 6]], [User::factory()->technician()->create(), [3, 4, 5, 6]]] as [$actor, $expected]) {
            $this->actingAs($actor);
            $widget = Livewire::test(IncidentStatusOverview::class)->assertSee('Incidencias no cerradas')
                ->assertSee('Propuesta')->assertSee('Abierta')->assertSee('En curso')->assertSee('Resuelta');
            $this->assertSame($expected, array_values($widget->instance()->counts()));
            $this->assertArrayNotHasKey('closed', $widget->instance()->counts());
        }
    }

    public function test_zeroes_registration_authentication_and_filter_independence(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        Livewire::test(IncidentStatusOverview::class)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create());
        $widget = Livewire::test(IncidentStatusOverview::class)->assertSee('0')
            ->assertSee('wire:poll.30s.visible', false);
        $this->assertSame(['proposed' => 0, 'open' => 0, 'in_progress' => 0, 'resolved' => 0], $widget->instance()->counts());
        $widgets = Filament::getPanel('admin')->getWidgets();
        $this->assertSame(1, count(array_filter($widgets, fn ($class) => $class === IncidentStatusOverview::class)));
        $this->assertContains(AccountWidget::class, $widgets);
        $this->get('/admin?tableFilters[status][value]=closed')->assertOk()->assertSee('Incidencias no cerradas');
        $this->assertStringNotContainsString('<a ', $widget->html());
    }

    public function test_poll_recalculates_after_creation_closure_and_user_change(): void
    {
        $this->actingAs($admin = User::factory()->admin()->create());
        $widget = Livewire::test(IncidentStatusOverview::class);
        $asset = Asset::factory()->create();
        app(CreateIncident::class)->handle($admin, ['title' => 'Nueva', 'description' => 'Para resumen', 'asset_id' => $asset->id]);
        $widget->call('$refresh');
        $this->assertSame(1, $widget->instance()->counts()['proposed']);
        $incident = Incident::factory()->assigned()->create(['status' => 'resolved']);
        $widget->call('$refresh');
        $this->assertSame(1, $widget->instance()->counts()['resolved']);
        app(TransitionIncident::class)->handle($admin, $incident, IncidentStatus::Resolved, IncidentStatus::Closed);
        $widget->call('$refresh');
        $this->assertSame(0, $widget->instance()->counts()['resolved']);
        $this->actingAs(User::factory()->create());
        $widget->call('$refresh');
        $this->assertSame([0, 0, 0, 0], array_values($widget->instance()->counts()));
    }
}
