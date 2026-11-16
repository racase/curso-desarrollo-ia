<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Actions\Catalog\SaveCatalogRecord;
use App\Filament\Resources\Sites\SiteResource;
use App\Filament\Support\FormErrors;
use App\Models\Site;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSite extends CreateRecord
{
    protected static string $resource = SiteResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return FormErrors::run(fn (): Model => app(SaveCatalogRecord::class)->handle(auth()->user(), new Site, $data));
    }

    protected function getRedirectUrl(): string
    {
        return SiteResource::getUrl('view', ['record' => $this->record]);
    }
}
