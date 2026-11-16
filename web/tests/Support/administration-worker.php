<?php

use App\Actions\Catalog\DeleteCatalogRecord;
use App\Actions\Tickets\AssignTicket;
use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\UpdateTicket;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\UpdateUser;
use App\Models\Asset;
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
DB::statement("SET application_name = 'infraticket-administration-worker'");
DB::statement("SET lock_timeout = '8s'");
$args = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
try {
    $actor = User::findOrFail($args['actor']);
    match ($args['operation']) {
        'update-user' => app(UpdateUser::class)->handle($actor, User::findOrFail($args['record']), $args['data']),
        'delete-user' => app(DeleteUser::class)->handle($actor, User::findOrFail($args['record'])),
        'assign' => app(AssignTicket::class)->handle($actor, Ticket::findOrFail($args['record']), $args['technician'], 0),
        'create-ticket' => app(CreateTicket::class)->handle($actor, $args['data']),
        'update-ticket' => app(UpdateTicket::class)->handle($actor, Ticket::findOrFail($args['record']), $args['data']),
        'delete-asset' => app(DeleteCatalogRecord::class)->handle($actor, Asset::findOrFail($args['record'])),
    };
    echo json_encode(['result' => 'saved']);
} catch (Throwable $exception) {
    echo json_encode(['error' => $exception::class, 'message' => $exception->getMessage()]);
}
