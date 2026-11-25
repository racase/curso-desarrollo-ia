<?php

use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\IncidentStatusHistory;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// A fresh random database is the only database this check migrates and drops.
$database = 'infraticket_concurrency_'.bin2hex(random_bytes(6));
$adminConfig = config('database.connections.pgsql');
$adminConfig['url'] = null;
$adminConfig['database'] = 'postgres';
config(['database.connections.concurrency_admin' => $adminConfig]);
$admin = DB::connection('concurrency_admin');
$admin->statement('CREATE DATABASE '.$database);
$barrier = sys_get_temp_dir().'/'.$database;
mkdir($barrier);
$workers = [];

function requireResult(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

try {
    config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => $database, 'database.connections.pgsql.url' => null]);
    DB::purge('pgsql');
    requireResult(Artisan::call('migrate', ['--force' => true]) === 0, 'Isolated migrations failed');
    echo "Migrations passed in isolated PostgreSQL database.\n";

    $ratingMigration = require database_path('migrations/2026_11_24_120000_add_resolution_rating_to_incidents_table.php');
    $ratingMigration->down();
    $legacy = Incident::factory()->assigned()->create(['status' => 'closed']);
    $unassigned = Incident::factory()->create(['status' => 'closed']);
    $pending = Incident::factory()->assigned()->create(['status' => 'resolved']);
    $comment = IncidentComment::factory()->create(['incident_id' => $legacy->id]);
    $history = IncidentStatusHistory::factory()->create(['incident_id' => $legacy->id]);
    $ratingMigration->up();
    requireResult($legacy->fresh()->closing_technician_id === $legacy->technician_id && $legacy->fresh()->resolution_rating === null, 'Historical attribution incorrect');
    requireResult($unassigned->fresh()->closing_technician_id === null && $pending->fresh()->closing_technician_id === null, 'Unexpected attribution');
    requireResult($comment->fresh() !== null && $history->fresh() !== null, 'Migration lost related rows');
    $ratingMigration->down();
    requireResult($legacy->fresh() !== null && $comment->fresh() !== null && $history->fresh() !== null, 'Rollback lost existing data');
    $ratingMigration->up();
    echo "Historical backfill and rollback passed; related rows preserved.\n";

    foreach (['assign', 'transition', 'rate', 'closure'] as $mode) {
        foreach (glob($barrier.'/*') as $file) {
            unlink($file);
        }
        $incident = match ($mode) {
            'rate' => Incident::factory()->closed()->create(),
            'closure' => Incident::factory()->assigned()->create(['status' => 'resolved']),
            default => Incident::factory()->create(),
        };
        $replacement = User::factory()->technician()->create();
        $actors = $mode === 'assign'
            ? [User::factory()->technician()->create(), User::factory()->technician()->create()]
            : ($mode === 'rate' ? [$incident->requester, $incident->requester] : [User::factory()->admin()->create(), User::factory()->admin()->create()]);
        DB::beginTransaction();
        Incident::query()->lockForUpdate()->findOrFail($incident->id);
        $workers = [];
        foreach ($actors as $index => $actor) {
            $worker = new Process([PHP_BINARY, __DIR__.'/incident-race-worker.php', $mode, (string) $incident->id, (string) $actor->id, $barrier, (string) $index], base_path(), [
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => $database, 'DB_URL' => '', 'RACE_TECHNICIAN_ID' => (string) $replacement->id,
            ]);
            $worker->setTimeout(25)->start();
            $workers[] = $worker;
        }
        $deadline = microtime(true) + 15;
        while (count(glob($barrier.'/ready-*')) !== 2) {
            requireResult(microtime(true) < $deadline, 'Workers did not reach the barrier');
            foreach ($workers as $worker) {
                requireResult($worker->isRunning(), 'Worker failed: '.$worker->getErrorOutput());
            }
            usleep(10000);
        }
        touch($barrier.'/go');
        // Wait until both independent connections contend on the parent row lock.
        do {
            DB::select('SELECT pg_stat_clear_snapshot()');
            $waiting = DB::selectOne("SELECT count(*) AS total FROM pg_stat_activity WHERE datname = ? AND wait_event_type = 'Lock'", [$database])->total;
            requireResult(microtime(true) < $deadline, 'Both workers did not contend on the row lock');
            usleep(10000);
        } while ((int) $waiting < 2);
        DB::commit();
        $results = [];
        foreach ($workers as $worker) {
            $worker->wait();
            requireResult($worker->isSuccessful(), 'Worker failed: '.$worker->getErrorOutput());
            $results[] = trim($worker->getOutput());
        }
        sort($results);
        if ($mode !== 'closure') {
            requireResult($results === ['accepted', 'conflict'], 'Expected one winner and one conflict: '.json_encode($results));
        }
        $incident->refresh();
        if ($mode === 'assign') {
            requireResult(in_array($incident->technician_id, array_map(fn (User $user) => $user->id, $actors), true), 'Unexpected technician');
            requireResult($incident->status === IncidentStatus::Proposed && $incident->statusHistory()->count() === 0, 'Assignment changed state or history');
        } elseif ($mode === 'rate') {
            requireResult(in_array($incident->resolution_rating, [3, 4], true) && $incident->rated_at !== null, 'Rating not saved exactly once');
            requireResult($incident->statusHistory()->count() === 0, 'Rating changed history');
        } elseif ($mode === 'closure') {
            requireResult(in_array('closed:'.$incident->closing_technician_id, $results, true) && in_array('accepted', $results, true), 'Closure snapshot changed after reassignment');
            requireResult($incident->technician_id === $replacement->id && $incident->status === IncidentStatus::Closed && $incident->statusHistory()->count() === 1, 'Closure and reassignment inconsistent');
        } else {
            requireResult($incident->status === IncidentStatus::Open && $incident->statusHistory()->count() === 1, 'Transition/history were duplicated');
        }
        echo "{$mode}: concurrency assertions passed; state and history consistent.\n";
    }
} finally {
    foreach ($workers as $worker) {
        if ($worker->isRunning()) {
            $worker->stop();
        }
    }
    if (DB::connection('pgsql')->transactionLevel() > 0) {
        DB::rollBack();
    }
    DB::disconnect('pgsql');
    $admin->statement('DROP DATABASE '.$database.' WITH (FORCE)');
    foreach (glob($barrier.'/*') as $file) {
        unlink($file);
    }
    rmdir($barrier);
}
