<?php

use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = 'infraticket_users_'.bin2hex(random_bytes(6));
$adminConfig = config('database.connections.pgsql');
$adminConfig['url'] = null;
$adminConfig['database'] = 'postgres';
config(['database.connections.concurrency_admin' => $adminConfig]);
$admin = DB::connection('concurrency_admin');
$admin->statement('CREATE DATABASE '.$database);
$barrier = sys_get_temp_dir().'/'.$database;
mkdir($barrier);
$workers = [];

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

try {
    config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => $database, 'database.connections.pgsql.url' => null]);
    DB::purge('pgsql');
    check(Artisan::call('migrate', ['--force' => true]) === 0, 'Isolated migrations failed');
    foreach (['demote', 'delete', 'technician'] as $mode) {
        foreach (glob($barrier.'/*') as $file) {
            unlink($file);
        }
        // Keep exactly two admins for each administrator race without deleting references.
        User::where('role', UserRole::Admin)->update(['role' => UserRole::Requester]);
        $first = User::factory()->admin()->create();
        $second = User::factory()->admin()->create();
        if ($mode === 'technician') {
            $technician = User::factory()->technician()->create();
            $incident = Incident::factory()->create();
            $jobs = [['assign', $technician->id, $incident->id], ['demote', $first->id, $technician->id]];
            $lockId = $technician->id;
        } else {
            $jobs = $mode === 'delete'
                ? [['delete', $first->id, $second->id], ['delete', $second->id, $first->id]]
                : [['demote', $first->id, $first->id], ['demote', $second->id, $second->id]];
            $lockId = $first->id;
        }
        DB::beginTransaction();
        User::query()->lockForUpdate()->findOrFail($lockId);
        $workers = [];
        foreach ($jobs as $index => [$operation, $actorId, $targetId]) {
            $worker = new Process([PHP_BINARY, __DIR__.'/user-race-worker.php', $operation, (string) $actorId, (string) $targetId, $barrier, (string) $index], base_path(), [
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => $database, 'DB_URL' => '',
            ]);
            $worker->setTimeout(25)->start();
            $workers[] = $worker;
        }
        $deadline = microtime(true) + 15;
        while (count(glob($barrier.'/ready-*')) !== 2) {
            check(microtime(true) < $deadline, 'Workers did not reach the barrier');
            foreach ($workers as $worker) {
                check($worker->isRunning(), 'Worker failed: '.$worker->getErrorOutput());
            }
            usleep(10000);
        }
        touch($barrier.'/go');
        do {
            DB::select('SELECT pg_stat_clear_snapshot()');
            $waiting = DB::selectOne("SELECT count(*) AS total FROM pg_stat_activity WHERE datname = ? AND wait_event_type = 'Lock'", [$database])->total;
            check(microtime(true) < $deadline, 'Workers did not contend on the row lock');
            usleep(10000);
        } while ((int) $waiting < 2);
        DB::commit();
        $results = [];
        foreach ($workers as $worker) {
            $worker->wait();
            check($worker->isSuccessful(), 'Worker failed: '.$worker->getErrorOutput());
            $results[] = trim($worker->getOutput());
        }
        sort($results);
        check($results === ['accepted', 'conflict'], 'Expected one winner and one conflict: '.json_encode($results));
        check(User::where('role', UserRole::Admin)->exists(), 'No administrator remains');
        if ($mode === 'technician') {
            $incident->refresh();
            check($incident->technician_id === null || $technician->fresh()->hasRole(UserRole::Technician), 'Assigned user lost technician role');
            check($incident->status->value === 'proposed' && $incident->statusHistory()->count() === 0, 'Partial incident mutation');
        }
        echo "{$mode}: one winner, one understandable conflict; invariants preserved.\n";
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
