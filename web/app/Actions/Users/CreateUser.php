<?php

namespace App\Actions\Users;

use App\Models\User;

class CreateUser
{
    public function handle(User $actor, array $input): User
    {
        $manager = app(ManageUsers::class);

        return $manager->transaction($actor, fn () => User::create($manager->validate($input)));
    }
}
