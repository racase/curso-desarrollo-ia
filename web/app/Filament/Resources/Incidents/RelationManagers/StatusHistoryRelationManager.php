<?php

namespace App\Filament\Resources\Incidents\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

class StatusHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistory';

    protected static ?string $title = 'Historial de estados';

    protected static bool $isLazy = false;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    #[On('incident-updated')]
    public function refreshHistory(int $incidentId): void
    {
        if ($this->getOwnerRecord()->id === $incidentId) {
            $this->resetTable();
        }
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with('actor'))
            ->columns([
                TextColumn::make('from_status')->label('Estado anterior')->badge(),
                TextColumn::make('to_status')->label('Estado nuevo')->badge(),
                TextColumn::make('actor.name')->label('Usuario'),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i:s'),
            ])->emptyStateHeading('Todavía no hay cambios de estado');
    }
}
