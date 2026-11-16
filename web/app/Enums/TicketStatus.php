<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TicketStatus: string implements HasLabel
{
    case Proposed = 'proposed';
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Proposed => 'Propuesta',
            self::Open => 'Abierta',
            self::InProgress => 'En curso',
            self::Resolved => 'Resuelta',
            self::Closed => 'Cerrada',
        };
    }

    public function next(): ?self
    {
        return match ($this) {
            self::Proposed => self::Open,
            self::Open => self::InProgress,
            self::InProgress => self::Resolved,
            self::Resolved => self::Closed,
            self::Closed => null,
        };
    }
}
