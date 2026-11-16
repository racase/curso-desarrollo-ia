<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(255)->autocomplete('name'),
            TextInput::make('email')->label('Correo')->email()->required()->maxLength(255)->autocomplete('username')->extraInputAttributes(['autocorrect' => 'off', 'autocapitalize' => 'none', 'spellcheck' => 'false']),
            Radio::make('role')->label('Rol')->options(UserRole::class)->required()->inline(),
            TextInput::make('password')->label('Contraseña')->password()->autocomplete('new-password')->minLength(12)->required(fn (string $operation): bool => $operation === 'create')->afterStateHydrated(fn (TextInput $component) => $component->state(''))->saved(fn (?string $state): bool => filled($state))->helperText('Al menos 12 caracteres. Al editar, déjala vacía para conservar la contraseña.'),
        ]);
    }
}
