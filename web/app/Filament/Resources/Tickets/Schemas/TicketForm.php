<?php

namespace App\Filament\Resources\Tickets\Schemas;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Models\Asset;
use App\Models\Ticket;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('title')->label('Título')->required()->minLength(3)->maxLength(200)->helperText('Entre 3 y 200 caracteres.'),
                Textarea::make('description')->label('Descripción')->required()->minLength(10)->maxLength(10000)->rows(5)->helperText('Entre 10 y 10.000 caracteres.'),
                Select::make('asset_id')->label('Activo')->relationship('asset', 'name', modifyQueryUsing: fn ($query) => $query->with('site'))->getOptionLabelFromRecordUsing(fn (Asset $record): string => $record->label())->saveRelationshipsUsing(null)->searchable(['code', 'name'])->placeholder('Sin activo asociado')->helperText('Opcional. Puedes registrar la incidencia aunque el activo no esté catalogado.'),
                Radio::make('category')->label('Categoría')->options(TicketCategory::class)->inline()
                    ->visible(fn (?Ticket $record): bool => $record !== null && auth()->user()->can('classify', $record))
                    ->helperText('Para clasificar, indica categoría y urgencia. Puedes conservar los valores actuales al editar otros datos.'),
                Radio::make('priority')->label('Urgencia')->options(TicketPriority::class)->inline()
                    ->visible(fn (?Ticket $record): bool => $record !== null && auth()->user()->can('classify', $record))
                    ->helperText('Se guarda como prioridad. Baja, media, alta o crítica según el impacto del problema.'),
            ]);
    }
}
