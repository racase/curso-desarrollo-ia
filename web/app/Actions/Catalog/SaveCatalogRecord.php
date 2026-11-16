<?php

namespace App\Actions\Catalog;

use App\Models\Asset;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveCatalogRecord
{
    public function handle(User $actor, Site|Asset $record, array $data): Site|Asset
    {
        try {
            return DB::transaction(function () use ($actor, $record, $data): Site|Asset {
                $actor = User::findOrFail($actor->id);
                if ($record->exists) {
                    $record = $record->newQuery()->lockForUpdate()->findOrFail($record->id);
                    Gate::forUser($actor)->authorize('update', $record);
                } else {
                    Gate::forUser($actor)->authorize('create', $record::class);
                }
                foreach (['code', 'name'] as $field) {
                    if (is_string($data[$field] ?? null)) {
                        $data[$field] = trim($data[$field]);
                    }
                }
                if (is_string($data['code'] ?? null)) {
                    $data['code'] = mb_strtoupper($data['code']);
                }
                $rules = [
                    'code' => ['required', 'string', 'max:50', Rule::unique($record->getTable(), 'code')->ignore($record->exists ? $record->id : null)],
                    'name' => ['required', 'string', 'max:200'],
                ];
                if ($record instanceof Asset) {
                    $rules['site_id'] = ['required', 'integer', Rule::exists('sites', 'id')];
                }
                $validated = Validator::make($data, $rules, [], ['code' => 'código', 'name' => 'nombre', 'site_id' => 'sede'])->validate();
                if ($record instanceof Asset && ! Site::whereKey($validated['site_id'])->sharedLock()->first()) {
                    throw ValidationException::withMessages(['site_id' => 'La sede seleccionada ya no existe.']);
                }
                $record->fill($validated)->save();

                return $record->refresh();
            });
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23505' || ((string) $exception->getCode() === '23000' && str_contains($exception->getMessage(), 'UNIQUE'))) {
                throw ValidationException::withMessages(['code' => 'Este código ya está registrado.']);
            }
            throw $exception;
        }
    }
}
