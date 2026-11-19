<?php

namespace App\Actions\Incidents;

use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CommentOnIncident
{
    public function handle(User $actor, Incident $incident, array $input): IncidentComment
    {
        $current = Incident::query()->findOrFail($incident->id);
        Gate::forUser($actor)->authorize('create', [IncidentComment::class, $current]);
        $data = Validator::make([
            'body' => is_string($input['body'] ?? null) ? trim($input['body']) : ($input['body'] ?? null),
        ], ['body' => ['required', 'string', 'max:5000']])->validate();

        return $current->comments()->create([...$data, 'author_id' => $actor->id]);
    }
}
