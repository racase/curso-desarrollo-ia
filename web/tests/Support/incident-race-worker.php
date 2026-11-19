<?php

use App\Actions\Incidents\AssignIncident;
use App\Actions\Incidents\TransitionIncident;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$script, $mode, $incidentId, $actorId, $barrier, $workerId] = $argv;
$incident = Incident::findOrFail($incidentId);
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
        app(AssignIncident::class)->handle($actor, $incident, $actor->id);
    } else {
        app(TransitionIncident::class)->handle($actor, $incident, IncidentStatus::Proposed, IncidentStatus::Open);
    }
    echo 'accepted';
} catch (ValidationException) {
    echo 'conflict';
}
