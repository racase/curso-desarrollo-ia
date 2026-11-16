<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Actions\Catalog\SaveCatalogRecord;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Support\FormErrors;
use App\Models\Asset;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAsset extends CreateRecord
{
    protected static string $resource = AssetResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return FormErrors::run(fn (): Model => app(SaveCatalogRecord::class)->handle(auth()->user(), new Asset, $data));
    }

    protected function getRedirectUrl(): string
    {
        return AssetResource::getUrl('view', ['record' => $this->record]);
    }
}
