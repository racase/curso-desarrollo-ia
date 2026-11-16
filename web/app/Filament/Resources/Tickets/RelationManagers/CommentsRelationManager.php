<?php

namespace App\Filament\Resources\Tickets\RelationManagers;

use App\Actions\Tickets\AddTicketComment;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CommentsRelationManager extends RelationManager
{
    use Concerns\RefreshesTicket;

    protected static string $relationship = 'comments';

    protected static ?string $title = 'Comentarios';

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
            TextColumn::make('body')->label('Comentario')->wrap(),
            TextColumn::make('author.name')->label('Autor'),
            TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i:s'),
        ])->headerActions([
            Action::make('comment')->label('Añadir comentario')
                ->authorize(fn (): bool => auth()->user()->can('comment', $this->getOwnerRecord()->fresh()))
                ->schema([Textarea::make('body')->label('Comentario')->required()->maxLength(5000)->rows(4)->helperText('Texto plano, hasta 5.000 caracteres.')])
                ->action(function (array $data): void {
                    try {
                        app(AddTicketComment::class)->handle(auth()->user(), $this->getOwnerRecord(), $data);
                    } catch (ValidationException $exception) {
                        $statePath = $this->getMountedActionSchema()->getStatePath();
                        throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $field): array => [$statePath.'.'.$field => $messages])->all());
                    }
                    Notification::make()->success()->title('Comentario publicado')->send();
                }),
        ])->emptyStateHeading('Todavía no hay comentarios');
    }
}
