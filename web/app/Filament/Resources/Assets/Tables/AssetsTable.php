<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Filament\Support\ProtectedDeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label('Código')->searchable()->sortable(),
            TextColumn::make('name')->label('Nombre')->searchable(),
            TextColumn::make('site.name')->label('Sede'),
        ])->recordActions([ViewAction::make(), EditAction::make(), ProtectedDeleteAction::make()])->defaultSort('id', 'desc');
    }
}
