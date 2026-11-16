<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdministrationMigrationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_migrations_reverse_and_reapply_on_sqlite(): void
    {
        $this->artisan('migrate:reset', ['--force' => true])->assertExitCode(0);
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertSame('users', DB::table('administration_locks')->sole()->name);
        $this->assertNotNull(Asset::factory()->create()->site);
    }

    public function test_normalization_rejects_collisions_without_changing_accounts(): void
    {
        $first = User::factory()->create(['email' => 'Case@example.com']);
        $second = User::factory()->create(['email' => 'case@example.com']);
        $migration = require database_path('migrations/2026_11_16_000004_prepare_user_administration.php');
        $migration->down();
        try {
            $migration->up();
            $this->fail('Collision accepted');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('correos duplicados', $exception->getMessage());
        }
        $this->assertSame('Case@example.com', $first->refresh()->email);
        $this->assertSame('case@example.com', $second->refresh()->email);
        $this->assertFalse(Schema::hasTable('administration_locks'));
        $second->delete();
        $migration->up();
        $this->assertSame('case@example.com', $first->refresh()->email);
        $this->assertSame('users', DB::table('administration_locks')->sole()->name);
    }

    public function test_asset_migration_preserves_existing_tickets_and_reverses(): void
    {
        $migration = require database_path('migrations/2026_11_16_000006_add_asset_to_tickets.php');
        $migration->down();
        $ticket = Ticket::factory()->create();
        $migration->up();
        $this->assertNull($ticket->refresh()->asset_id);
        $this->assertNotNull($ticket->requester);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('tickets', 'asset_id'));
        $migration->up();
        $this->assertModelExists($ticket);
    }
}
