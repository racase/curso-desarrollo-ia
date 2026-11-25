<?php

namespace App\Filament\Widgets;

use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TechnicianRatings extends TableWidget
{
    protected static ?string $heading = 'Valoraciones por técnico';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole(UserRole::Admin) ?? false;
    }

    public function boot(): void
    {
        abort_unless(static::canView(), 403);
    }

    public function ratingsQuery(): Builder
    {
        abort_unless(static::canView(), 403);
        $ratings = Incident::query()->whereNotNull('closing_technician_id')
            ->selectRaw('closing_technician_id, AVG(resolution_rating) AS rating_average, COUNT(resolution_rating) AS rating_count')
            ->groupBy('closing_technician_id');

        return User::query()->leftJoinSub($ratings, 'ratings', 'users.id', '=', 'ratings.closing_technician_id')
            ->where(fn (Builder $query) => $query->where('role', UserRole::Technician)->orWhereNotNull('ratings.closing_technician_id'))
            ->select('users.*', 'ratings.rating_average')->selectRaw('COALESCE(ratings.rating_count, 0) AS rating_count')
            ->orderBy('users.name')->orderBy('users.id');
    }

    public function table(Table $table): Table
    {
        return $table->extraAttributes(['class' => 'it-technician-ratings'])->query(fn (): Builder => $this->ratingsQuery())
            ->columns([
                TextColumn::make('name')->label('Técnico')->wrap()
                    ->description(fn (User $record): ?string => $record->hasRole(UserRole::Technician) ? null : 'Histórico'),
                TextColumn::make('rating_average')->label('Media sobre 5')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 1, ',', '.').' / 5')
                    ->placeholder('Sin valoraciones')->wrap(),
                TextColumn::make('rating_count')->label('Valoraciones')->wrap(),
            ])->paginated([5, 10, 25])->defaultPaginationPageOption(5)
            ->poll('30s.visible')->emptyStateHeading('No hay técnicos');
    }
}
