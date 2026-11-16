<?php

namespace App\Filament\Support;

use App\Actions\Catalog\DeleteCatalogRecord;
use App\Actions\Users\DeleteUser;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ProtectedDeleteAction
{
    public static function make(): DeleteAction
    {
        return DeleteAction::make()->using(function (Model $record, DeleteAction $action): bool {
            try {
                return $record instanceof User
                    ? app(DeleteUser::class)->handle(auth()->user(), $record)
                    : app(DeleteCatalogRecord::class)->handle(auth()->user(), $record);
            } catch (ValidationException $exception) {
                Notification::make()->danger()->title('No se puede eliminar')->body(collect($exception->errors())->flatten()->implode(' '))->send();
                $action->halt();

                return false;
            }
        });
    }
}
