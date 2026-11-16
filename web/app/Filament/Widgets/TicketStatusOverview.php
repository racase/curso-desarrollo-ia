<?php

namespace App\Filament\Widgets;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TicketStatusOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Incidencias pendientes de cierre';

    protected ?string $pollingInterval = null;

    public static function canView(): bool
    {
        return auth()->check();
    }

    public function counts(): array
    {
        abort_unless(auth()->check(), 403);
        $states = [TicketStatus::Proposed, TicketStatus::Open, TicketStatus::InProgress, TicketStatus::Resolved];
        $counts = Ticket::visibleTo(auth()->user())->whereIn('status', $states)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $result = [];
        foreach ($states as $state) {
            $result[$state->value] = (int) ($counts[$state->value] ?? 0);
        }

        return $result;
    }

    protected function getStats(): array
    {
        return collect($this->counts())->map(fn (int $count, string $state): Stat => Stat::make(TicketStatus::from($state)->getLabel(), $count))->values()->all();
    }
}
