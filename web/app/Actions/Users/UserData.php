<?php

namespace App\Actions\Users;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserData
{
    public static function validate(array $data, ?User $user = null): array
    {
        foreach (['name', 'email'] as $field) {
            if (is_string($data[$field] ?? null)) {
                $data[$field] = trim($data[$field]);
            }
        }
        if (is_string($data['email'] ?? null)) {
            $data['email'] = mb_strtolower($data['email']);
        }
        if ($user && ($data['password'] ?? '') === '') {
            unset($data['password']);
        }

        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => [$user ? 'sometimes' : 'required', 'string', 'min:12'],
        ], [], ['name' => 'nombre', 'email' => 'correo', 'role' => 'rol', 'password' => 'contraseña'])->validate();
    }
}
