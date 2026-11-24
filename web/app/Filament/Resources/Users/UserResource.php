<?php

namespace App\Filament\Resources\Users;

use App\Actions\Users\DeleteUser;
use App\Enums\UserRole;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nombre')->required()->maxLength(255)->autocomplete('name'),
            TextInput::make('email')->label('Email')->email()->required()->maxLength(255)
                ->autocomplete('email')->extraInputAttributes(['autocapitalize' => 'none', 'spellcheck' => 'false']),
            Select::make('role')->label('Rol')->options(UserRole::class)->required(),
            TextInput::make('password')->label('Contraseña')->password()->autocomplete('new-password')
                ->afterStateHydrated(fn (TextInput $component) => $component->state(''))
                ->helperText('Mínimo 12 caracteres. Al editar, déjala vacía para conservar la actual.')
                ->required(fn (string $operation): bool => $operation === 'create')
                ->minLength(12)->confirmed(),
            TextInput::make('password_confirmation')->label('Confirmar contraseña')->password()
                ->autocomplete('new-password'),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nombre')->searchable()->wrap(),
            TextColumn::make('email')->label('Email')->searchable()->wrap(),
            TextColumn::make('role')->label('Rol'),
        ])->recordActions([EditAction::make()->label('Editar'), self::deleteAction()])
            ->emptyStateHeading('No hay usuarios');
    }

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()->label('Eliminar')->requiresConfirmation()
            ->modalDescription('La cuenta solo se eliminará si no tiene referencias y se conserva el acceso administrativo.')
            ->using(function (User $record): bool {
                try {
                    app(DeleteUser::class)->handle(auth()->user(), $record);
                } catch (ValidationException $exception) {
                    Notification::make()->title('No se pudo eliminar el usuario')->body($exception->getMessage())->danger()->send();
                    throw new Halt;
                }

                return true;
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
