<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum IncidentPriority: string implements HasLabel
{
    case Unclassified = 'unclassified';
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unclassified => 'Sin clasificar',
            self::Low => 'Baja',
            self::Medium => 'Media',
            self::High => 'Alta',
            self::Critical => 'Crítica',
        };
    }
}
