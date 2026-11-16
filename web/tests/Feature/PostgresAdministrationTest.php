<?php

namespace Tests\Feature;

use App\Actions\Catalog\DeleteCatalogRecord;
use App\Actions\Tickets\AssignTicket;
use App\Actions\Tickets\CreateTicket;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\UpdateUser;
use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\Support\PostgresTestDatabase;
use Tests\TestCase;

class PostgresAdministrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! getenv('PG_TEST_HOST')) {
            $this->markTestSkipped('Usa compose.testing.yaml para PostgreSQL aislado.');
        }
        PostgresTestDatabase::configure();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    private function data(User $user): array
    {
        return ['name' => $user->name, 'email' => $user->email, 'role' => 'requester'];
    }

    private function ticketData(Asset $asset): array
    {
        return ['title' => 'Caso concurrente', 'description' => 'No funciona el equipo.', 'priority' => 'medium', 'asset_id' => $asset->id];
    }

    private function race(array $args, callable $lock, callable $winner): array
    {
        $process = new Process([PHP_BINARY, base_path('tests/Support/administration-worker.php'), json_encode($args, JSON_THROW_ON_ERROR)], base_path());
        $process->setTimeout(12);
        DB::beginTransaction();
        try {
            $lock();
            $process->start();
            $deadline = microtime(true) + 6;
            $blocked = false;
            do {
                DB::select('SELECT pg_stat_clear_snapshot()');
                $blocked = DB::selectOne("SELECT count(*) AS count FROM pg_stat_activity WHERE application_name = 'infraticket-administration-worker' AND wait_event_type = 'Lock'")->count > 0;
                if ($blocked) {
                    break;
                }
                usleep(20000);
            } while ($process->isRunning() && microtime(true) < $deadline);
            $this->assertTrue($blocked, 'Worker did not wait: '.$process->getErrorOutput().$process->getOutput());
            $winner();
            DB::commit();
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());

            return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $process->stop();
        }
    }

    private function lockUsers(): void
    {
        DB::table('administration_locks')->where('name', 'users')->lockForUpdate()->sole();
    }

    public function test_concurrent_demotions_preserve_last_administrator(): void
    {
        $first = User::factory()->admin()->create();
        $second = User::factory()->admin()->create();
        $result = $this->race(['operation' => 'update-user', 'actor' => $second->id, 'record' => $second->id, 'data' => $this->data($second)], fn () => $this->lockUsers(), fn () => app(UpdateUser::class)->handle($first, $first, $this->data($first)));
        $this->assertSame(ValidationException::class, $result['error']);
        $this->assertSame(1, User::where('role', UserRole::Admin)->count());
    }

    public function test_concurrent_deletions_preserve_last_administrator(): void
    {
        $first = User::factory()->admin()->create();
        $second = User::factory()->admin()->create();
        $result = $this->race(['operation' => 'delete-user', 'actor' => $second->id, 'record' => $second->id], fn () => $this->lockUsers(), fn () => app(DeleteUser::class)->handle($first, $first));
        $this->assertSame(ValidationException::class, $result['error']);
        $this->assertSame(1, User::where('role', UserRole::Admin)->count());
    }

    public function test_role_change_before_assignment_rejects_invalid_technician(): void
    {
        $admin = User::factory()->admin()->create();
        $tech = User::factory()->technician()->create();
        $ticket = Ticket::factory()->create();
        $result = $this->race(['operation' => 'assign', 'actor' => $admin->id, 'record' => $ticket->id, 'technician' => $tech->id], fn () => $this->lockUsers(), fn () => app(UpdateUser::class)->handle($admin, $tech, $this->data($tech)));
        $this->assertSame(ValidationException::class, $result['error']);
        $this->assertNull($ticket->refresh()->technician_id);
    }

    public function test_assignment_before_role_change_preserves_technician(): void
    {
        $admin = User::factory()->admin()->create();
        $tech = User::factory()->technician()->create();
        $ticket = Ticket::factory()->create();
        $result = $this->race(['operation' => 'update-user', 'actor' => $admin->id, 'record' => $tech->id, 'data' => $this->data($tech)], fn () => $this->lockUsers(), fn () => app(AssignTicket::class)->handle($admin, $ticket, $tech->id, 0));
        $this->assertSame(ValidationException::class, $result['error']);
        $this->assertSame(UserRole::Technician, $tech->refresh()->role);
        $this->assertSame($tech->id, $ticket->refresh()->technician_id);
    }

    public function test_asset_deleted_during_create_is_rejected_without_partial_ticket(): void
    {
        $admin = User::factory()->admin()->create();
        $asset = Asset::factory()->create();
        $result = $this->race(['operation' => 'create-ticket', 'actor' => $admin->id, 'data' => $this->ticketData($asset)], fn () => Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail(), fn () => app(DeleteCatalogRecord::class)->handle($admin, $asset));
        $this->assertSame(ValidationException::class, $result['error']);
        $this->assertSame(0, Ticket::count());
        $this->assertModelMissing($asset);
    }

    public function test_asset_deleted_during_update_preserves_ticket(): void
    {
        $admin = User::factory()->admin()->create();
        $asset = Asset::factory()->create();
        $ticket = Ticket::factory()->create();
        $original = $ticket->title;
        $result = $this->race(['operation' => 'update-ticket', 'actor' => $admin->id, 'record' => $ticket->id, 'data' => $this->ticketData($asset)], fn () => Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail(), fn () => app(DeleteCatalogRecord::class)->handle($admin, $asset));
        $this->assertSame(ValidationException::class, $result['error']);
        $this->assertSame($original, $ticket->refresh()->title);
        $this->assertNull($ticket->asset_id);
    }

    public function test_ticket_saved_before_asset_deletion_preserves_reference(): void
    {
        $admin = User::factory()->admin()->create();
        $asset = Asset::factory()->create();
        $result = $this->race(['operation' => 'delete-asset', 'actor' => $admin->id, 'record' => $asset->id], fn () => Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail(), fn () => app(CreateTicket::class)->handle($admin, $this->ticketData($asset)));
        $this->assertSame(ValidationException::class, $result['error']);
        $this->assertSame($asset->id, Ticket::sole()->asset_id);
        $this->assertModelExists($asset);
    }

    public function test_catalogue_constraints_migrations_and_legacy_data_on_postgres(): void
    {
        $migration = require database_path('migrations/2026_11_16_000006_add_asset_to_tickets.php');
        $migration->down();
        $ticket = Ticket::factory()->create();
        $migration->up();
        $this->assertNull($ticket->refresh()->asset_id);
        $normalization = require database_path('migrations/2026_11_16_000004_prepare_user_administration.php');
        $normalization->down();
        $first = User::factory()->create(['email' => 'Case@example.com']);
        User::factory()->create(['email' => 'case@example.com']);
        try {
            $normalization->up();
            $this->fail('Normalized collision accepted');
        } catch (\RuntimeException) {
            $this->assertSame('Case@example.com', $first->refresh()->email);
        }
        User::where('email', 'case@example.com')->delete();
        $normalization->up();
        $this->assertSame('case@example.com', $first->refresh()->email);
        $asset = Asset::factory()->create();
        $ticket->update(['asset_id' => $asset->id]);
        foreach ([$asset, $asset->site] as $record) {
            try {
                $record->delete();
                $this->fail('Referenced catalogue record deleted');
            } catch (QueryException $exception) {
                $this->assertSame('23503', (string) $exception->getCode());
            }
        }
        try {
            Asset::factory()->create(['code' => $asset->code]);
            $this->fail('Duplicate code accepted');
        } catch (QueryException $exception) {
            $this->assertSame('23505', (string) $exception->getCode());
        }
        $this->artisan('migrate:reset', ['--force' => true])->assertExitCode(0);
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->assertSame('users', DB::table('administration_locks')->sole()->name);
    }
}
