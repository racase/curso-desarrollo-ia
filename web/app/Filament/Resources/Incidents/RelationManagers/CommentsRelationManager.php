<?php

namespace App\Filament\Resources\Incidents\RelationManagers;

use App\Actions\Incidents\CommentOnIncident;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Comentarios';

    protected static bool $isLazy = false;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn ($query) => $query->with('author'))
            ->columns([
                TextColumn::make('body')->label('Comentario')->wrap(),
                TextColumn::make('author.name')->label('Autor'),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i:s'),
            ])->headerActions([
                Action::make('comment')->label('Añadir comentario')
                    ->authorize(fn (): bool => Gate::allows('view', $this->getOwnerRecord()))
                    ->schema([Textarea::make('body')->label('Comentario')->required()->maxLength(5000)])
                    ->action(function (array $data): void {
                        app(CommentOnIncident::class)->handle(auth()->user(), $this->getOwnerRecord(), $data);
                        $this->resetTable();
                    }),
            ])->emptyStateHeading('Todavía no hay comentarios')->paginationPageOptions([10, 25, 50]);
    }
}
