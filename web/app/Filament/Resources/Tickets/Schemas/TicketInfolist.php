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
                TextEntry::make('priority')->label('Prioridad')->badge(),
                TextEntry::make('requester.name')->label('Solicitante'),
                TextEntry::make('technician.name')->label('Técnico asignado')->placeholder('Sin asignar'),
                TextEntry::make('created_at')->label('Alta en propuesta')->dateTime('d/m/Y H:i'),
            ]);
    }
}
