<?php

namespace App\Filament\Resources\Tickets\Tables;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('N.º')->sortable(),
                TextColumn::make('title')->label('Título')->searchable()->wrap(),
                TextColumn::make('status')->label('Estado')->badge(),
                TextColumn::make('category')->label('Categoría')->badge()->placeholder('Sin clasificar'),
                TextColumn::make('priority')->label('Urgencia')->badge()->placeholder('Sin clasificar'),
                TextColumn::make('requester.name')->label('Solicitante'),
                TextColumn::make('technician.name')->label('Técnico')->placeholder('Sin asignar'),
                TextColumn::make('created_at')->label('Fecha de alta')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Estado')->options(TicketStatus::class),
                SelectFilter::make('priority')->label('Urgencia')->options(TicketPriority::class),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }
}
