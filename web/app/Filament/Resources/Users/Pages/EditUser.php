<?php

namespace App\Filament\Resources\Users\Pages;

use App\Actions\Users\UpdateUser;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\FormErrors;
use App\Filament\Support\ProtectedDeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return FormErrors::run(fn (): Model => app(UpdateUser::class)->handle(auth()->user(), $record, $data));
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), ProtectedDeleteAction::make()];
    }
}
