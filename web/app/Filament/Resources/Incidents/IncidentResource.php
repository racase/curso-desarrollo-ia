<?php

namespace App\Filament\Resources\Incidents;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Filament\Resources\Incidents\Pages\CreateIncident;
use App\Filament\Resources\Incidents\Pages\ListIncidents;
use App\Filament\Resources\Incidents\Pages\ViewIncident;
use App\Models\Asset;
use App\Models\Incident;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class IncidentResource extends Resource
{
    protected static ?string $model = Incident::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static ?string $modelLabel = 'incidencia';

    protected static ?string $pluralModelLabel = 'incidencias';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->label('Título')->required()->maxLength(255),
            Textarea::make('description')->label('Descripción')->required()->maxLength(10000)->columnSpanFull(),
            Select::make('asset_id')->label('Activo y sede')->options(fn (): array => Asset::groupedOptions())
                ->searchable()->required()->columnSpanFull(),
            Select::make('priority')->label('Prioridad')->options(IncidentPriority::class)
                ->default(IncidentPriority::Medium->value)->required()->native(false),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title')->label('Título'),
            TextEntry::make('description')->label('Descripción')->columnSpanFull(),
            TextEntry::make('status')->label('Estado')->badge(),
            TextEntry::make('priority')->label('Prioridad')->badge(),
            TextEntry::make('requester.name')->label('Solicitante'),
            TextEntry::make('technician.name')->label('Técnico')->placeholder('Sin asignar'),
            TextEntry::make('asset_id')->label('Activo')
                ->formatStateUsing(fn (Incident $record): ?string => $record->asset ? $record->asset->code.' · '.$record->asset->name : null)
                ->placeholder('Sin activo'),
            TextEntry::make('asset.site.name')->label('Sede')->placeholder('Sin sede'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label('Título')->searchable()->wrap(),
            TextColumn::make('status')->label('Estado')->badge(),
            TextColumn::make('priority')->label('Prioridad')->badge(),
            TextColumn::make('requester.name')->label('Solicitante'),
            TextColumn::make('asset.code')->label('Activo')->description(fn (Incident $record): ?string => $record->asset?->name)
                ->placeholder('Sin activo')->wrap(),
            TextColumn::make('asset.site.name')->label('Sede')->placeholder('Sin sede')->wrap(),
        ])->filters([
            SelectFilter::make('status')->label('Estado')->options(IncidentStatus::class),
            SelectFilter::make('priority')->label('Prioridad')->options(IncidentPriority::class),
        ])->recordActions([ViewAction::make()->label('Ver')])
            ->defaultSort('id', 'desc')->emptyStateHeading('No hay incidencias');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())->with(['requester', 'technician', 'asset.site']);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StatusHistoryRelationManager::class,
            RelationManagers\CommentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIncidents::route('/'),
            'create' => CreateIncident::route('/create'),
            'view' => ViewIncident::route('/{record}'),
        ];
    }
}
