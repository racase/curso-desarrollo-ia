<?php

namespace Tests\Feature;

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\AssignTicket;
use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\TransitionTicket;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Process;
use Tests\Support\PostgresTestDatabase;
use Tests\TestCase;

class PostgresTicketTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! getenv('PG_TEST_HOST')) {
            $this->markTestSkipped('Usa compose.testing.yaml para las pruebas PostgreSQL aisladas.');
        }
        PostgresTestDatabase::configure();
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
    }

    public function test_migrations_can_be_reversed_and_reapplied_in_postgres(): void
    {
        $this->artisan('migrate:reset', ['--force' => true])->assertExitCode(0);
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $ticket = Ticket::factory()->create();
        $this->assertNotNull($ticket->requester);
    }

    public function test_concurrent_transition_rejects_stale_version(): void
    {
        $actor = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();
        $result = $this->race($ticket, ['transition', $ticket->id, $actor->id, 'open', 0], function () use ($actor, $ticket): void {
            app(TransitionTicket::class)->handle($actor, $ticket, TicketStatus::Open, 0);
        });
        $this->assertSame(ValidationException::class, $result['error']);
        $this->assertSame(TicketStatus::Open, $ticket->refresh()->status);
        $this->assertSame(1, $ticket->statusChanges()->count());
    }

    public function test_concurrent_reassignment_cannot_overwrite_previous_change(): void
    {
        $actor = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create();
        $first = User::factory()->technician()->create();
        $second = User::factory()->technician()->create();
        $result = $this->race($ticket, ['assign', $ticket->id, $actor->id, $second->id, 0], function () use ($actor, $ticket, $first): void {
            app(AssignTicket::class)->handle($actor, $ticket, $first->id, 0);
        });
        $this->assertSame(ValidationException::class, $result['error']);
        $this->assertSame($first->id, $ticket->refresh()->technician_id);
        $this->assertSame(1, $ticket->tracking_version);
    }

    public function test_concurrent_close_prevents_comment_publication(): void
    {
        $actor = User::factory()->admin()->create();
        $ticket = Ticket::factory()->create(['status' => 'resolved', 'technician_id' => User::factory()->technician()]);
        $result = $this->race($ticket, ['comment', $ticket->id, $actor->id], function () use ($actor, $ticket): void {
            app(TransitionTicket::class)->handle($actor, $ticket, TicketStatus::Closed, 0);
        });
        $this->assertSame(AuthorizationException::class, $result['error']);
        $this->assertSame(TicketStatus::Closed, $ticket->refresh()->status);
        $this->assertSame(0, $ticket->comments()->count());
    }

    private function race(Ticket $ticket, array $arguments, callable $winner): array
    {
        $process = new Process([PHP_BINARY, base_path('tests/Support/ticket-worker.php'), ...array_map('strval', $arguments)], base_path());
        $process->setTimeout(12);
        DB::beginTransaction();
        try {
            if ($arguments[0] === 'assign') {
                DB::table('administration_locks')->where('name', 'users')->lockForUpdate()->sole();
            }
            Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            $process->start();
            $deadline = microtime(true) + 6;
            do {
                DB::select('SELECT pg_stat_clear_snapshot()');
                $blocked = DB::selectOne("SELECT count(*) AS count FROM pg_stat_activity WHERE application_name = 'infraticket-test-worker' AND wait_event_type = 'Lock'")->count > 0;
                if ($blocked) {
                    break;
                }
                usleep(20000);
            } while ($process->isRunning() && microtime(true) < $deadline);
            $this->assertTrue($blocked, 'Worker did not wait on the row lock: '.$process->getErrorOutput().$process->getOutput());
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

    public function test_complete_workflow_with_all_roles_on_postgres(): void
    {
        $requester = User::factory()->create();
        $technician = User::factory()->technician()->create();
        $admin = User::factory()->admin()->create();
        $ticket = app(CreateTicket::class)->handle($requester, ['title' => 'Incidencia de integración', 'description' => 'La impresora no responde.', 'priority' => 'medium']);
        $ticket = app(AssignTicket::class)->handle($admin, $ticket, $technician->id, 0);
        $ticket = app(TransitionTicket::class)->handle($technician, $ticket, TicketStatus::Open, 1);
        $ticket = app(TransitionTicket::class)->handle($technician, $ticket, TicketStatus::InProgress, 2);
        app(AddTicketComment::class)->handle($requester, $ticket, ['body' => 'Añado información.']);
        app(AddTicketComment::class)->handle($technician, $ticket, ['body' => 'He localizado el problema.']);
        $ticket = app(TransitionTicket::class)->handle($technician, $ticket, TicketStatus::Resolved, 3);
        $ticket = app(TransitionTicket::class)->handle($admin, $ticket, TicketStatus::Closed, 4);
        $this->assertSame(4, $ticket->statusChanges()->count());
        $this->assertSame(2, $ticket->comments()->count());
        foreach ([$requester, $technician, $admin] as $actor) {
            $this->assertTrue(Gate::forUser($actor)->allows('view', $ticket));
            foreach (['update', 'assign', 'transition', 'comment', 'delete'] as $ability) {
                $this->assertFalse(Gate::forUser($actor)->allows($ability, $ticket));
            }
        }
        $other = User::factory()->create();
        $this->assertFalse(Gate::forUser($other)->allows('view', $ticket));
        $this->assertSame(0, Ticket::visibleTo($other)->count());
    }
}
