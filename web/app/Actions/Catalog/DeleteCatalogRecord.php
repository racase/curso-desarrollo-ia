<?php

namespace App\Actions\Catalog;

use App\Models\Asset;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class DeleteCatalogRecord
{
    public function handle(User $actor, Site|Asset $record): bool
    {
        try {
            return DB::transaction(function () use ($actor, $record): bool {
                $record = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
                Gate::forUser(User::findOrFail($actor->id))->authorize('delete', $record);
                if (($record instanceof Site && $record->assets()->exists()) || ($record instanceof Asset && $record->tickets()->exists())) {
                    throw ValidationException::withMessages(['record' => 'No se puede eliminar un registro con información relacionada.']);
                }

                return $record->delete();
            });
        } catch (QueryException $exception) {
            if (! in_array((string) $exception->getCode(), ['23000', '23503'], true)) {
                throw $exception;
            }
            throw ValidationException::withMessages(['record' => 'El registro tiene información relacionada y no puede eliminarse.']);
        }
    }
}
