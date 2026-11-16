<?php

namespace App\Filament\Resources\Sites\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('code')->label('Código')->required()->maxLength(50)->helperText('Código único, hasta 50 caracteres. Se guarda en mayúsculas.')->extraInputAttributes(['autocorrect' => 'off', 'autocapitalize' => 'characters', 'spellcheck' => 'false']),
            TextInput::make('name')->label('Nombre')->required()->maxLength(200),
        ]);
    }
}
