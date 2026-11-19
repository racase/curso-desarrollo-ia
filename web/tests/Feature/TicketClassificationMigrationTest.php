<?php

namespace Tests\Feature;

use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\PostgresTestDatabase;
use Tests\TestCase;

class TicketClassificationMigrationTest extends TestCase
{
    public function test_migration_preserves_history_and_reverses_without_inventing_priorities(): void
    {
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $this->assertMigrationRoundTrip();
    }

    public function test_migration_round_trip_on_isolated_postgres(): void
    {
        if (! getenv('PG_TEST_HOST')) {
            $this->markTestSkipped('Requiere PostgreSQL de pruebas aislado.');
        }
        PostgresTestDatabase::configure();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        $this->assertMigrationRoundTrip();
    }

    private function assertMigrationRoundTrip(): void
    {
        $migration = require database_path('migrations/2026_11_18_000001_add_classification_to_tickets.php');
        $migration->down();
        $ticket = Ticket::factory()->create(['priority' => 'high']);
        $migration->up();
        $this->assertSame('high', $ticket->refresh()->priority->value);
        $this->assertNull($ticket->category);
        $this->assertFalse($ticket->isClassified());
        $empty = Ticket::factory()->unclassified()->create();
        $this->assertFalse($empty->isClassified());
        $complete = Ticket::factory()->classified()->create();
        $this->assertTrue($complete->isClassified());
        try {
            $migration->down();
            $this->fail('Rollback accepted null priority');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Clasifica', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasColumn('tickets', 'category'));
        DB::table('tickets')->where('id', $empty->id)->update(['priority' => 'low']);
        $migration->down();
        $this->assertFalse(Schema::hasColumn('tickets', 'category'));
        $migration->up();
        $this->assertSame('high', $ticket->refresh()->priority->value);
    }
}
