<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Users\CreateUser as CreateUserAction;
use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(CreateUserAction::class)->handle(auth()->user(), $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(
                fn (array $messages, string $key): array => ["data.{$key}" => $messages],
            )->all());
        }
    }
}
