<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TicketCategory: string implements HasLabel
{
    case Hardware = 'hardware';
    case Software = 'software';
    case Network = 'network';
    case Access = 'access';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Hardware => 'Hardware',
            self::Software => 'Software',
            self::Network => 'Red',
            self::Access => 'Accesos',
            self::Other => 'Otros',
        };
    }
}
