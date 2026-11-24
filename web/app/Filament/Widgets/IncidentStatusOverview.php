<?php

namespace App\Filament\Widgets;

use App\Enums\IncidentStatus;
use App\Models\Incident;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Computed;

class IncidentStatusOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Incidencias no cerradas';

    protected ?string $description = 'Incluye todos los estados salvo Cerrada. Solicitantes: solo sus incidencias; equipo: todas. Actualización cada 30 segundos.';

    protected ?string $pollingInterval = '30s.visible';

    protected static bool $isLazy = false;

    protected int|array|null $columns = ['default' => 1, 'md' => 2, 'xl' => 4];

    public static function canView(): bool
    {
        return auth()->check();
    }

    #[Computed]
    public function counts(): array
    {
        abort_unless(auth()->check(), 403);
        $totals = Incident::query()->visibleTo(auth()->user())
            ->where('status', '!=', IncidentStatus::Closed->value)
            ->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $counts = [];
        foreach (IncidentStatus::cases() as $status) {
            if ($status !== IncidentStatus::Closed) {
                $counts[$status->value] = (int) ($totals[$status->value] ?? 0);
            }
        }

        return $counts;
    }

    protected function getStats(): array
    {
        $stats = [];
        foreach ($this->counts as $status => $total) {
            $stats[] = Stat::make(IncidentStatus::from($status)->getLabel(), $total);
        }

        return $stats;
    }
}
