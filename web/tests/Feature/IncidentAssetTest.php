<?php

namespace Tests\Feature;

use App\Actions\Incidents\CreateIncident;
use App\Actions\Incidents\LinkIncidentAsset;
use App\Enums\IncidentStatus;
use App\Enums\UserRole;
use App\Filament\Resources\Incidents\Pages\CreateIncident as CreateIncidentPage;
use App\Filament\Resources\Incidents\Pages\ListIncidents;
use App\Filament\Resources\Incidents\Pages\ViewIncident;
use App\Models\Asset;
use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\IncidentStatusHistory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class IncidentAssetTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_roles_create_with_an_asset_and_cannot_spoof_its_site(): void
    {
        $asset = Asset::factory()->create();
        $different = Asset::factory()->create();
        foreach (UserRole::cases() as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $incident = app(CreateIncident::class)->handle($actor, [
                'title' => 'Equipo', 'description' => 'No arranca', 'asset_id' => $asset->id,
                'site_id' => $different->site_id, 'priority' => 'critical', 'status' => 'closed',
                'requester_id' => 999999, 'technician_id' => 999999,
            ]);
            $this->assertSame($asset->site_id, $incident->asset->site_id);
            $this->assertSame($actor->id, $incident->requester_id);
            $this->assertNull($incident->technician_id);
            $this->assertSame(IncidentStatus::Proposed, $incident->status);
            $this->assertSame('critical', $incident->priority->value);
        }
    }

    public function test_missing_and_invalid_assets_are_rejected_by_create_and_link_actions(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = Incident::factory()->withoutAsset()->create();
        foreach ([null, '', 999999, 'not-an-id', [], 1.5] as $invalid) {
            foreach ([fn () => app(CreateIncident::class)->handle($admin, ['title' => 'Equipo', 'description' => 'No arranca', 'asset_id' => $invalid]),
                fn () => app(LinkIncidentAsset::class)->handle($admin, $incident, ['asset_id' => $invalid])] as $operation) {
                try {
                    $operation();
                    $this->fail('Invalid asset accepted');
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey('asset_id', $exception->errors());
                }
            }
        }
        $this->assertDatabaseCount('incidents', 1);
        $this->assertNull($incident->fresh()->asset_id);
    }

    public function test_link_changes_only_asset_in_every_state_even_with_a_stale_snapshot(): void
    {
        $admin = User::factory()->admin()->create();
        $asset = Asset::factory()->create();
        foreach (IncidentStatus::cases() as $status) {
            $incident = Incident::factory()->withoutAsset()->assigned()->create(['status' => $status]);
            $comment = IncidentComment::factory()->create(['incident_id' => $incident->id]);
            $history = IncidentStatusHistory::factory()->create(['incident_id' => $incident->id]);
            $before = $incident->fresh()->getRawOriginal();
            $incident->title = 'Stale snapshot must not overwrite persisted title';
            $linked = app(LinkIncidentAsset::class)->handle($admin, $incident, ['asset_id' => $asset->id, 'status' => 'closed']);
            foreach ($before as $key => $value) {
                if (! in_array($key, ['asset_id', 'updated_at'], true)) {
                    $this->assertSame($value, $linked->getRawOriginal($key));
                }
            }
            $this->assertSame($asset->id, $linked->asset_id);
            $this->assertModelExists($comment);
            $this->assertSame(1, $linked->statusHistory()->count());
            $this->assertModelExists($history);
            $replacement = Asset::factory()->create();
            $this->assertSame($replacement->id, app(LinkIncidentAsset::class)->handle($admin, $linked, ['asset_id' => $replacement->id])->asset_id);
        }
    }

    public function test_unauthorized_link_actions_are_rejected(): void
    {
        $incident = Incident::factory()->withoutAsset()->create();
        $asset = Asset::factory()->create();
        foreach ([$incident->requester, User::factory()->technician()->create()] as $actor) {
            try {
                app(LinkIncidentAsset::class)->handle($actor, $incident, ['asset_id' => $asset->id]);
                $this->fail('Unauthorized correction accepted');
            } catch (AuthorizationException) {
                $this->assertNull($incident->fresh()->asset_id);
            }
            $this->actingAs($actor);
            Livewire::test(ViewIncident::class, ['record' => $incident->id])->assertActionHidden('linkAsset')
                ->call('mountAction', 'linkAsset')->assertSet('mountedActions', []);
            $this->assertNull($incident->fresh()->asset_id);
        }
    }

    public function test_panel_selection_is_unambiguous_and_legacy_can_be_corrected(): void
    {
        $first = Asset::factory()->create(['name' => 'Activo 01']);
        $second = Asset::factory()->create(['name' => 'Activo 01']);
        $this->actingAs($admin = User::factory()->admin()->create());
        $options = Asset::groupedOptions();
        $this->assertSame($first->label(), $options[$first->site->code][$first->id]);
        $this->assertSame($second->label(), $options[$second->site->code][$second->id]);
        Livewire::test(CreateIncidentPage::class)->fillForm(['title' => 'Panel', 'description' => 'Con activo', 'asset_id' => $second->id])
            ->call('create')->assertHasNoFormErrors();
        $this->assertSame($second->id, Incident::sole()->asset_id);
        $legacy = Incident::factory()->withoutAsset()->create();
        Livewire::test(ListIncidents::class)->assertCanSeeTableRecords([$legacy])->assertSee('Sin activo')->assertSee('Sin sede');
        Livewire::test(ViewIncident::class, ['record' => $legacy->id])->assertSee('Sin activo')->assertSee('Sin sede')
            ->callAction('linkAsset', ['asset_id' => $first->id])->assertHasNoActionErrors()->assertSee($first->code)->assertSee($first->site->name);
        $this->assertSame($first->id, $legacy->fresh()->asset_id);
        $this->get('/admin/incidents/'.$legacy->id.'/edit')->assertNotFound();
        Livewire::test(CreateIncidentPage::class)->fillForm(['title' => 'Panel', 'description' => 'Sin activo'])
            ->call('create')->assertHasFormErrors(['asset_id']);
    }
}
