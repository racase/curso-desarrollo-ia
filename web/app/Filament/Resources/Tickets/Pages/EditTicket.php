<?php

namespace App\Filament\Resources\Tickets\Pages;

use App\Actions\Tickets\TicketData;
use App\Actions\Tickets\UpdateTicket;
use App\Filament\Resources\Tickets\TicketResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    protected function beforeValidate(): void
    {
        if (TicketData::classificationChanges($this->getRecord(), $this->data ?? [])) {
            Gate::authorize('classify', $this->getRecord());
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(UpdateTicket::class)->handle(auth()->user(), $record, $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn (array $messages, string $field): array => ['data.'.$field => $messages])->all());
        }
    }
}
