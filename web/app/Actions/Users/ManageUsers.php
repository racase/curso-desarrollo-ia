<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ManageUsers
{
    public function transaction(User $actor, callable $operation): mixed
    {
        return DB::transaction(function () use ($actor, $operation) {
            // All administrative writers use the same ordered lock before target locks.
            User::query()->where('role', UserRole::Admin)->orderBy('id')->lockForUpdate()->get();
            $currentActor = User::query()->lockForUpdate()->find($actor->id);
            Gate::forUser($currentActor)->authorize('viewAny', User::class);

            return $operation();
        }, 3);
    }

    public function validate(array $input, ?User $user = null): array
    {
        $data = array_intersect_key($input, array_flip(['name', 'email', 'role', 'password', 'password_confirmation']));
        foreach (['name', 'email'] as $field) {
            if (is_string($data[$field] ?? null)) {
                $data[$field] = trim($data[$field]);
            }
        }
        if (is_string($data['email'] ?? null)) {
            $data['email'] = mb_strtolower($data['email']);
        }
        if ($user && ($data['password'] ?? '') === '') {
            unset($data['password'], $data['password_confirmation']);
        }

        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => [$user ? 'sometimes' : 'required', 'string', 'min:12', 'confirmed'],
        ])->validate();
    }
}
