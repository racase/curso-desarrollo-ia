<?php

namespace App\Filament\Resources\Tickets\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('id')->label('Identificador'),
                TextEntry::make('title')->label('Título'),
                TextEntry::make('description')->label('Descripción')->columnSpanFull(),
                TextEntry::make('status')->label('Estado')->badge(),
                TextEntry::make('category')->label('Categoría')->badge()->placeholder('Sin clasificar'),
                TextEntry::make('priority')->label('Urgencia')->badge()->placeholder('Sin clasificar'),
                TextEntry::make('requester.name')->label('Solicitante'),
                TextEntry::make('technician.name')->label('Técnico asignado')->placeholder('Sin asignar'),
                TextEntry::make('asset.code')->label('Código del activo')->placeholder('Sin activo asociado'),
                TextEntry::make('asset.name')->label('Activo')->placeholder('Sin activo asociado'),
                TextEntry::make('asset.site.name')->label('Sede')->placeholder('Sin activo asociado'),
                TextEntry::make('created_at')->label('Alta en propuesta')->dateTime('d/m/Y H:i'),
            ]);
    }
}
