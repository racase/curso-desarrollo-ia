<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class IncidentCommentPolicy
{
    public function view(User $user, IncidentComment $comment): bool
    {
        return Gate::forUser($user)->allows('view', $comment->incident);
    }

    public function create(User $user, Incident $incident): bool
    {
        return Gate::forUser($user)->allows('view', $incident);
    }

    public function update(User $user, IncidentComment $comment): bool
    {
        return false;
    }

    public function delete(User $user, IncidentComment $comment): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
