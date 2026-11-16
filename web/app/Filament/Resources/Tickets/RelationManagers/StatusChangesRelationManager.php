<?php

namespace App\Filament\Resources\Tickets\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class StatusChangesRelationManager extends RelationManager
{
    use Concerns\RefreshesTicket;

    protected static string $relationship = 'statusChanges';

    protected static ?string $title = 'Historial de estados';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()->can('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(function (Builder $query): Builder {
            Gate::authorize('view', $this->getOwnerRecord()->fresh());

            return $query->with('author');
        })->columns([
            TextColumn::make('from_status')->label('Estado anterior'),
            TextColumn::make('to_status')->label('Nuevo estado'),
            TextColumn::make('author.name')->label('Autor'),
            TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i:s'),
        ])->emptyStateHeading('Sin cambios de estado');
    }
}
