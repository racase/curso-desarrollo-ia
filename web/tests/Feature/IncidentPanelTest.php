<?php

namespace Tests\Feature;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Filament\Resources\Incidents\IncidentResource;
use App\Filament\Resources\Incidents\Pages\CreateIncident;
use App\Filament\Resources\Incidents\Pages\ListIncidents;
use App\Filament\Resources\Incidents\Pages\ViewIncident;
use App\Models\Asset;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IncidentPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_panel_creation_uses_defaults_and_displays_details(): void
    {
        $this->actingAs($user = User::factory()->create());
        Livewire::test(CreateIncident::class)->assertFormSet(['priority' => IncidentPriority::Medium])
            ->fillForm(['title' => 'Equipo', 'description' => 'No arranca', 'asset_id' => Asset::factory()->create()->id])
            ->call('create')->assertHasNoFormErrors();
        $incident = Incident::sole();
        $this->assertSame('proposed', $incident->status->value);
        $this->assertSame($user->id, $incident->requester_id);
        Livewire::test(ViewIncident::class, ['record' => $incident->id])->assertSee('No arranca')->assertSee('Sin asignar');
    }

    public function test_filters_are_combined_and_do_not_leak_records(): void
    {
        $this->actingAs($user = User::factory()->create());
        $own = Incident::factory()->create(['requester_id' => $user->id, 'status' => 'open', 'priority' => 'high']);
        $different = Incident::factory()->create(['requester_id' => $user->id]);
        $other = Incident::factory()->create(['status' => 'open', 'priority' => 'high']);
        Livewire::test(ListIncidents::class)->assertCanSeeTableRecords([$own, $different])->assertCanNotSeeTableRecords([$other])
            ->filterTable('status', 'open')->filterTable('priority', 'high')
            ->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$different, $other])
            ->resetTableFilters()->assertCanSeeTableRecords([$own, $different]);
        foreach (IncidentStatus::cases() as $status) {
            $record = Incident::factory()->create(['requester_id' => $user->id, 'status' => $status]);
            Livewire::test(ListIncidents::class)->filterTable('status', $status->value)->assertCanSeeTableRecords([$record]);
        }
    }

    public function test_direct_access_is_protected_and_edit_routes_do_not_exist(): void
    {
        $incident = Incident::factory()->create();
        $url = IncidentResource::getUrl('view', ['record' => $incident]);
        $this->get($url)->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get($url)->assertNotFound();
        $this->get($url.'/edit')->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
        $this->actingAs(User::factory()->technician()->create())->get($url)->assertOk();
    }

    public function test_text_is_escaped_and_empty_list_is_clear(): void
    {
        $this->actingAs($user = User::factory()->create());
        Livewire::test(ListIncidents::class)->assertSee('No hay incidencias');
        $incident = Incident::factory()->create(['requester_id' => $user->id, 'title' => '<script>alert(1)</script>', 'description' => '<b>Texto</b>']);
        Livewire::test(ViewIncident::class, ['record' => $incident->id])->assertSee('&lt;b&gt;Texto&lt;/b&gt;', false)->assertDontSee('<b>Texto</b>', false);
        Livewire::test(ListIncidents::class)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_invalid_panel_fields_show_errors(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(CreateIncident::class)->fillForm(['title' => ' ', 'description' => ' ', 'priority' => 'medium'])
            ->call('create')->assertHasFormErrors(['title', 'description']);
        $this->assertDatabaseCount('incidents', 0);
    }
}
