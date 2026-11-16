<?php

namespace Tests\Feature;

use App\Actions\Catalog\SaveCatalogRecord;
use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\UpdateTicket;
use App\Filament\Resources\Tickets\Pages\CreateTicket as CreateTicketPage;
use App\Filament\Resources\Tickets\Pages\EditTicket;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Models\Asset;
use App\Models\Site;
use App\Models\Ticket;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TicketAssetsTest extends TestCase
{
    use RefreshDatabase;

    private function data(array $overrides = []): array
    {
        return array_replace(['title' => 'Fallo de equipo', 'description' => 'El equipo no arranca.', 'priority' => 'medium'], $overrides);
    }

    public function test_optional_asset_and_edit_preserve_replace_or_remove_reference(): void
    {
        $owner = User::factory()->create();
        $asset = Asset::factory()->create();
        $other = Asset::factory()->create();
        $ticket = app(CreateTicket::class)->handle($owner, $this->data());
        $this->assertNull($ticket->asset_id);
        $ticket = app(UpdateTicket::class)->handle($owner, $ticket, $this->data(['asset_id' => $asset->id]));
        $this->assertSame($asset->id, $ticket->asset_id);
        $ticket = app(UpdateTicket::class)->handle($owner, $ticket, $this->data());
        $this->assertSame($asset->id, $ticket->asset_id);
        $ticket = app(UpdateTicket::class)->handle($owner, $ticket, $this->data(['asset_id' => $other->id]));
        $this->assertSame($other->id, $ticket->asset_id);
        $ticket = app(UpdateTicket::class)->handle($owner, $ticket, $this->data(['asset_id' => null]));
        $this->assertNull($ticket->asset_id);
        $created = app(CreateTicket::class)->handle($owner, $this->data(['asset_id' => $asset->id]));
        $this->assertSame($asset->id, $created->asset_id);
    }

    public function test_invalid_asset_rejects_entire_create_or_update(): void
    {
        $owner = User::factory()->create();
        $ticket = Ticket::factory()->create(['requester_id' => $owner->id]);
        $original = $ticket->title;
        foreach ([fn () => app(CreateTicket::class)->handle($owner, $this->data(['asset_id' => 999])), fn () => app(UpdateTicket::class)->handle($owner, $ticket, $this->data(['asset_id' => 999]))] as $operation) {
            try {
                $operation();
                $this->fail('Invalid reference accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('asset_id', $exception->errors());
                $this->assertDatabaseCount('tickets', 1);
                $this->assertSame($original, $ticket->refresh()->title);
            }
        }
    }

    public function test_asset_cannot_bypass_existing_edit_permissions(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $staff = User::factory()->technician()->create();
        $admin = User::factory()->admin()->create();
        $asset = Asset::factory()->create();
        foreach ([['proposed', $other], ['in_progress', $owner], ['closed', $owner], ['closed', $staff], ['closed', $admin]] as [$status, $actor]) {
            $ticket = Ticket::factory()->create(['requester_id' => $owner->id, 'status' => $status]);
            try {
                app(UpdateTicket::class)->handle($actor, $ticket, $this->data(['asset_id' => $asset->id]));
                $this->fail('Unauthorized asset edit');
            } catch (AuthorizationException) {
                $this->assertNull($ticket->refresh()->asset_id);
            }
        }
    }

    public function test_panel_selects_asset_displays_context_and_preserves_privacy(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $asset = Asset::factory()->create();
        Livewire::test(CreateTicketPage::class)->fillForm($this->data(['asset_id' => $asset->id]))->call('create')->assertHasNoFormErrors();
        $ticket = Ticket::sole();
        $this->assertSame($asset->id, $ticket->asset_id);
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])->assertSee($asset->code)->assertSee($asset->name)->assertSee($asset->site->name);
        $site = Site::factory()->create(['name' => 'Nueva ubicación']);
        app(SaveCatalogRecord::class)->handle(User::factory()->admin()->create(), $asset, ['code' => $asset->code, 'name' => $asset->name, 'site_id' => $site->id]);
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])->assertSee('Nueva ubicación');
        Livewire::test(EditTicket::class, ['record' => $ticket->id])->fillForm(['asset_id' => null])->call('save')->assertHasNoFormErrors();
        $this->assertNull($ticket->refresh()->asset_id);
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])->assertSee('Sin activo asociado');
        $this->actingAs(User::factory()->create());
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])->assertNotFound();
    }
}
