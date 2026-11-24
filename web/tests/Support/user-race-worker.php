<?php

use App\Actions\Incidents\AssignIncident;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\UpdateUser;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$script, $mode, $actorId, $targetId, $barrier, $workerId] = $argv;
$actor = User::findOrFail($actorId);
touch("{$barrier}/ready-{$workerId}");
$deadline = microtime(true) + 15;
while (! file_exists("{$barrier}/go")) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Race barrier timed out');
    }
    usleep(10000);
}
try {
    if ($mode === 'assign') {
        app(AssignIncident::class)->handle($actor, Incident::findOrFail($targetId), $actor->id);
    } elseif ($mode === 'delete') {
        app(DeleteUser::class)->handle($actor, User::findOrFail($targetId));
    } else {
        $target = User::findOrFail($targetId);
        app(UpdateUser::class)->handle($actor, $target, [
            'name' => $target->name, 'email' => $target->email, 'role' => 'requester',
        ]);
    }
    echo 'accepted';
} catch (ValidationException|AuthorizationException) {
    echo 'conflict';
}
