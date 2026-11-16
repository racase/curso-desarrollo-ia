<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserAdministration
{
    public static function lock(User $actor): User
    {
        DB::table('administration_locks')->where('name', 'users')->lockForUpdate()->sole();

        return User::findOrFail($actor->id);
    }
}
