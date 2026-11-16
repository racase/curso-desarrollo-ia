<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Actions\Catalog\SaveCatalogRecord;
use App\Filament\Resources\Sites\SiteResource;
use App\Filament\Support\FormErrors;
use App\Filament\Support\ProtectedDeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSite extends EditRecord
{
    protected static string $resource = SiteResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return FormErrors::run(fn (): Model => app(SaveCatalogRecord::class)->handle(auth()->user(), $record, $data));
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), ProtectedDeleteAction::make()];
    }
}
