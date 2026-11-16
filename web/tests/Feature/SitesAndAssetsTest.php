<?php

namespace Tests\Feature;

use App\Actions\Catalog\DeleteCatalogRecord;
use App\Actions\Catalog\SaveCatalogRecord;
use App\Models\Asset;
use App\Models\Site;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SitesAndAssetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_maintain_catalogue_and_move_assets(): void
    {
        $admin = User::factory()->admin()->create();
        $site = app(SaveCatalogRecord::class)->handle($admin, new Site, ['code' => ' mad ', 'name' => ' Madrid ']);
        $this->assertSame('MAD', $site->code);
        $this->assertSame('Madrid', $site->name);
        $asset = app(SaveCatalogRecord::class)->handle($admin, new Asset, ['code' => ' pc-1 ', 'name' => ' Ordenador ', 'site_id' => $site->id]);
        $other = Site::factory()->create();
        app(SaveCatalogRecord::class)->handle($admin, $asset, ['code' => $asset->code, 'name' => $asset->name, 'site_id' => $other->id]);
        $this->assertSame($other->id, $asset->refresh()->site_id);
        $this->assertStringContainsString($other->name, $asset->label());
    }

    public function test_catalogue_validates_normalized_uniqueness_and_required_site(): void
    {
        $admin = User::factory()->admin()->create();
        $site = Site::factory()->create(['code' => 'MAD']);
        foreach ([[new Site, ['code' => ' mad ', 'name' => 'Madrid'], 'code'], [new Site, ['code' => 'NEW', 'name' => '  '], 'name'], [new Site, ['code' => str_repeat('x', 51), 'name' => 'Madrid'], 'code'], [new Asset, ['code' => 'PC', 'name' => 'PC'], 'site_id'], [new Asset, ['code' => 'PC', 'name' => 'PC', 'site_id' => 999], 'site_id']] as [$record, $data, $field]) {
            try {
                app(SaveCatalogRecord::class)->handle($admin, $record, $data);
                $this->fail('Invalid record accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }
        $this->assertDatabaseCount('sites', 1);
        $this->assertDatabaseCount('assets', 0);
    }

    public function test_references_block_deletion_without_cascades(): void
    {
        $admin = User::factory()->admin()->create();
        $asset = Asset::factory()->create();
        $ticket = Ticket::factory()->create(['asset_id' => $asset->id, 'status' => 'closed']);
        foreach ([$asset, $asset->site] as $record) {
            try {
                app(DeleteCatalogRecord::class)->handle($admin, $record);
                $this->fail('Referenced record deleted');
            } catch (ValidationException) {
                $this->assertModelExists($record);
                $this->assertModelExists($ticket);
            }
        }
        $freeAsset = Asset::factory()->create();
        $freeSite = $freeAsset->site;
        app(DeleteCatalogRecord::class)->handle($admin, $freeAsset);
        app(DeleteCatalogRecord::class)->handle($admin, $freeSite);
        $this->assertModelMissing($freeAsset);
        $this->assertModelMissing($freeSite);
    }

    public function test_other_roles_cannot_write_catalogue(): void
    {
        $asset = Asset::factory()->create();
        foreach ([User::factory()->create(), User::factory()->technician()->create()] as $actor) {
            foreach ([$asset, $asset->site] as $record) {
                foreach ([fn () => app(SaveCatalogRecord::class)->handle($actor, $record, []), fn () => app(SaveCatalogRecord::class)->handle($actor, $record->newInstance(), []), fn () => app(DeleteCatalogRecord::class)->handle($actor, $record)] as $operation) {
                    try {
                        $operation();
                        $this->fail('Unauthorized catalogue write');
                    } catch (AuthorizationException) {
                        $this->assertModelExists($record);
                    }
                }
            }
        }
    }
}
