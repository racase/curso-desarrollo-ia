<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\IncidentStatusHistory;
use App\Models\Site;
use Database\Seeders\SiteAssetSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SiteAssetCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_is_exact_repeatable_and_preserves_ids_and_links(): void
    {
        $this->seed(SiteAssetSeeder::class);
        $sites = Site::orderBy('code')->get();
        $assets = Asset::orderBy('code')->get();
        $incident = Incident::factory()->create(['asset_id' => $assets->first()->id]);
        $this->seed(SiteAssetSeeder::class);
        $this->assertSame($sites->pluck('id')->all(), Site::orderBy('code')->pluck('id')->all());
        $this->assertSame($assets->pluck('id')->all(), Asset::orderBy('code')->pluck('id')->all());
        $this->assertDatabaseCount('sites', 6);
        $this->assertDatabaseCount('assets', 30);
        foreach ($sites as $index => $site) {
            $suffix = sprintf('%02d', $index + 1);
            $this->assertSame('S'.$suffix, $site->code);
            $this->assertSame('Sede '.$suffix, $site->name);
            $this->assertCount(5, $site->assets);
            foreach ($site->assets as $assetIndex => $asset) {
                $assetSuffix = sprintf('%02d', $assetIndex + 1);
                $this->assertSame($site->code.'-A'.$assetSuffix, $asset->code);
                $this->assertSame('Activo '.$assetSuffix, $asset->name);
            }
        }
        $this->assertSame($assets->first()->id, $incident->fresh()->asset_id);
    }

    public function test_unexpected_catalog_rolls_back_without_deleting_existing_data(): void
    {
        $unexpected = Site::factory()->create();
        try {
            $this->seed(SiteAssetSeeder::class);
            $this->fail('Unexpected catalog accepted');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Catálogo inesperado', $exception->getMessage());
        }
        $this->assertDatabaseCount('sites', 1);
        $this->assertDatabaseCount('assets', 0);
        $this->assertModelExists($unexpected);
        $unexpected->delete();
        $this->seed(SiteAssetSeeder::class);
        $asset = Asset::first();
        $asset->update(['site_id' => Site::whereKeyNot($asset->site_id)->first()->id]);
        try {
            $this->seed(SiteAssetSeeder::class);
            $this->fail('Wrong asset ownership accepted');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($asset->code, $exception->getMessage());
        }
        $this->assertSame($asset->site_id, $asset->fresh()->site_id);
    }

    public function test_factories_persist_valid_and_legacy_records_without_demo_seeding(): void
    {
        $incident = Incident::factory()->create();
        $legacy = Incident::factory()->withoutAsset()->create();
        $this->assertNotNull($incident->asset->site);
        $this->assertTrue($incident->asset->incidents->contains($incident));
        $this->assertNull($legacy->asset);
        $this->assertDatabaseCount('sites', 1);
    }

    public function test_catalog_migrations_preserve_an_incident_and_all_references_on_old_schema(): void
    {
        $catalog = require database_path('migrations/2026_11_24_100000_create_sites_and_assets_tables.php');
        $link = require database_path('migrations/2026_11_24_100100_add_asset_id_to_incidents_table.php');
        $link->down();
        $catalog->down();
        $incident = Incident::create([
            'title' => 'Incidencia anterior', 'description' => 'Datos que deben conservarse',
            'status' => 'open', 'priority' => 'high',
            'requester_id' => \App\Models\User::factory()->create()->id,
            'technician_id' => \App\Models\User::factory()->technician()->create()->id,
        ]);
        $comment = IncidentComment::factory()->create(['incident_id' => $incident->id]);
        $history = IncidentStatusHistory::factory()->create(['incident_id' => $incident->id]);
        $before = $incident->fresh()->getRawOriginal();
        $catalog->up();
        $link->up();
        $this->assertSame([...$before, 'asset_id' => null], $incident->fresh()->getRawOriginal());
        $this->assertModelExists($comment);
        $this->assertModelExists($history);
        $this->seed(SiteAssetSeeder::class);
        $this->seed(SiteAssetSeeder::class);
        $this->assertNull($incident->fresh()->asset_id);
    }

    public function test_unique_codes_required_ownership_and_restrictive_foreign_keys(): void
    {
        $asset = Asset::factory()->create();
        $incident = Incident::factory()->create(['asset_id' => $asset->id]);
        foreach ([fn () => Site::factory()->create(['code' => $asset->site->code]),
            fn () => Asset::factory()->create(['code' => $asset->code]),
            fn () => Asset::factory()->create(['site_id' => null]),
            fn () => Asset::factory()->create(['site_id' => 999999]),
            fn () => $asset->site->delete(), fn () => $asset->delete(),
            fn () => $incident->update(['asset_id' => 999999])] as $operation) {
            try {
                $operation();
                $this->fail('Integrity violation accepted');
            } catch (QueryException) {
                $this->assertModelExists($asset);
                $this->assertModelExists($asset->site);
            }
        }
    }
}
