<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum IncidentCategory: string implements HasLabel
{
    case Unclassified = 'unclassified';
    case Hardware = 'hardware';
    case Software = 'software';
    case Network = 'network';
    case Access = 'access';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Unclassified => 'Sin clasificar',
            self::Hardware => 'Hardware',
            self::Software => 'Software',
            self::Network => 'Red',
            self::Access => 'Accesos',
            self::Other => 'Otros',
        };
    }
}
