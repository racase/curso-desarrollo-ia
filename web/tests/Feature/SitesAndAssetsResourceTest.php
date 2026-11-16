<?php

namespace Tests\Feature;

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Assets\Pages\CreateAsset;
use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Filament\Resources\Assets\Pages\ViewAsset;
use App\Filament\Resources\Sites\Pages\CreateSite;
use App\Filament\Resources\Sites\Pages\EditSite;
use App\Filament\Resources\Sites\Pages\ListSites;
use App\Filament\Resources\Sites\Pages\ViewSite;
use App\Models\Asset;
use App\Models\Site;
use App\Models\Ticket;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SitesAndAssetsResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_administrator_can_maintain_catalogue_from_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(CreateSite::class)->fillForm(['code' => 'MAD', 'name' => 'Madrid'])->call('create')->assertHasNoFormErrors();
        $site = Site::sole();
        Livewire::test(CreateAsset::class)->fillForm(['code' => 'PC-1', 'name' => 'Ordenador', 'site_id' => $site->id])->call('create')->assertHasNoFormErrors();
        $asset = Asset::sole();
        Livewire::test(EditAsset::class, ['record' => $asset->id])->fillForm(['name' => 'Portátil'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Portátil', $asset->refresh()->name);
        Livewire::test(ViewSite::class, ['record' => $site->id])->callAction('delete')->assertNotified('No se puede eliminar');
        Livewire::test(ViewAsset::class, ['record' => $asset->id])->callAction('delete');
        Livewire::test(ViewSite::class, ['record' => $site->id])->callAction('delete');
        $this->assertModelMissing($asset);
        $this->assertModelMissing($site);
    }

    public function test_catalogue_is_read_only_for_non_admins_and_does_not_reveal_tickets(): void
    {
        $asset = Asset::factory()->create();
        Ticket::factory()->create(['asset_id' => $asset->id, 'title' => 'Incidencia confidencial ajena']);
        $this->get(AssetResource::getUrl())->assertRedirect('/admin/login');
        foreach ([User::factory()->create(), User::factory()->technician()->create()] as $actor) {
            $this->actingAs($actor);
            Livewire::test(ListAssets::class)->assertCanSeeTableRecords([$asset]);
            Livewire::test(ListSites::class)->assertCanSeeTableRecords([$asset->site]);
            Livewire::test(ViewAsset::class, ['record' => $asset->id])->assertOk()->assertDontSee('Incidencia confidencial ajena')->assertActionHidden('edit')->assertActionHidden('delete');
            Livewire::test(ViewSite::class, ['record' => $asset->site_id])->assertOk();
            Livewire::test(CreateSite::class)->assertForbidden();
            Livewire::test(CreateAsset::class)->assertForbidden();
            Livewire::test(EditSite::class, ['record' => $asset->site_id])->assertForbidden();
            Livewire::test(EditAsset::class, ['record' => $asset->id])->assertForbidden();
        }
    }
}
