<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Support\ProtectedDeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nombre')->searchable(),
            TextColumn::make('email')->label('Correo')->searchable(),
            TextColumn::make('role')->label('Rol')->badge(),
        ])->recordActions([ViewAction::make(), EditAction::make(), ProtectedDeleteAction::make()])->defaultSort('id', 'desc');
    }
}
