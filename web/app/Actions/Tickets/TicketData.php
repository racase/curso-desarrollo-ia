<?php

namespace App\Actions\Tickets;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Models\Asset;
use App\Models\Ticket;
use BackedEnum;
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

        return Validator::make($data, [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
            'asset_id' => ['sometimes', 'nullable', 'integer', Rule::exists('assets', 'id')],
        ], [], ['title' => 'título', 'description' => 'descripción', 'priority' => 'prioridad', 'asset_id' => 'activo'])->validate();
    }

    public static function lockAsset(array $data): void
    {
        if (isset($data['asset_id']) && ! Asset::whereKey($data['asset_id'])->sharedLock()->first()) {
            throw ValidationException::withMessages(['asset_id' => 'El activo seleccionado ya no existe.']);
        }
    }

    public static function classificationChanges(Ticket $ticket, array $data): bool
    {
        foreach (['category', 'priority'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field] instanceof BackedEnum ? $data[$field]->value : $data[$field];
            $current = $ticket->{$field}?->value;
            if (($value === '' ? null : $value) !== $current) {
                return true;
            }
        }

        return false;
    }

    public static function classification(array $data): array
    {
        return Validator::make($data, [
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
        ], [], ['category' => 'categoría', 'priority' => 'urgencia'])->validate();
    }
}
