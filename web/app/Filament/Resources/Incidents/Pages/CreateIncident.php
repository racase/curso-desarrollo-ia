<?php

namespace App\Filament\Resources\Incidents\Pages;

use App\Actions\Incidents\CreateIncident as CreateIncidentAction;
use App\Filament\Resources\Incidents\IncidentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateIncident extends CreateRecord
{
    protected static string $resource = IncidentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateIncidentAction::class)->handle(auth()->user(), $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(
                fn (array $messages, string $key): array => ["data.{$key}" => $messages],
            )->all());
        }
    }

    protected function getRedirectUrl(): string
    {
        return IncidentResource::getUrl('view', ['record' => $this->record]);
    }
}
