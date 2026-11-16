<?php

use App\Actions\Tickets\AddTicketComment;
use App\Actions\Tickets\AssignTicket;
use App\Actions\Tickets\TransitionTicket;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Tests\Support\PostgresTestDatabase;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->useEnvironmentPath(__DIR__)->loadEnvironmentFrom('testing-no-environment');
$app->make(Kernel::class)->bootstrap();
PostgresTestDatabase::configure();
DB::statement("SET application_name = 'infraticket-test-worker'");
DB::statement("SET lock_timeout = '8s'");

try {
    $ticket = Ticket::findOrFail((int) $argv[2]);
    $actor = User::findOrFail((int) $argv[3]);
    match ($argv[1]) {
        'transition' => app(TransitionTicket::class)->handle($actor, $ticket, TicketStatus::from($argv[4]), (int) $argv[5]),
        'assign' => app(AssignTicket::class)->handle($actor, $ticket, (int) $argv[4], (int) $argv[5]),
        'comment' => app(AddTicketComment::class)->handle($actor, $ticket, ['body' => 'Comentario concurrente']),
    };
    echo json_encode(['result' => 'saved']);
} catch (Throwable $exception) {
    echo json_encode(['error' => $exception::class, 'message' => $exception->getMessage()]);
}
