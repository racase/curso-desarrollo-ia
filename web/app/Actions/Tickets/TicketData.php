<?php

namespace App\Actions\Tickets;

use App\Enums\TicketPriority;
use App\Models\Asset;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketData
{
    public static function validate(array $data): array
    {
        foreach (['title', 'description'] as $field) {
            if (is_string($data[$field] ?? null)) {
                $data[$field] = trim($data[$field]);
            }
        }

        if (array_key_exists('asset_id', $data) && $data['asset_id'] === '') {
            $data['asset_id'] = null;
        }

        $validated = Validator::make($data, [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'asset_id' => ['sometimes', 'nullable', 'integer', Rule::exists('assets', 'id')],
        ], [], ['title' => 'título', 'description' => 'descripción', 'priority' => 'prioridad', 'asset_id' => 'activo'])->validate();
        if (isset($validated['asset_id']) && ! Asset::whereKey($validated['asset_id'])->sharedLock()->first()) {
            throw ValidationException::withMessages(['asset_id' => 'El activo seleccionado ya no existe.']);
        }

        return $validated;
    }
}
