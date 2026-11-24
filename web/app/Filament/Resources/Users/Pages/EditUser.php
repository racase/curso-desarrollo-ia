<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Users\UpdateUser;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(UpdateUser::class)->handle(auth()->user(), $record, $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(
                fn (array $messages, string $key): array => ["data.{$key}" => $messages],
            )->all());
        }
    }

    protected function getHeaderActions(): array
    {
        return [UserResource::deleteAction()];
    }
}
