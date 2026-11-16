<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CreateUser
{
    public function handle(User $actor, array $data): User
    {
        return DB::transaction(function () use ($actor, $data): User {
            $actor = UserAdministration::lock($actor);
            Gate::forUser($actor)->authorize('create', User::class);

            return User::create(UserData::validate($data));
        });
    }
}
