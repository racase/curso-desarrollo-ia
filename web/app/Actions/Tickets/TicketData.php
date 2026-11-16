<?php

namespace App\Actions\Tickets;

use App\Enums\TicketPriority;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TicketData
{
    public static function validate(array $data): array
    {
        foreach (['title', 'description'] as $field) {
            if (is_string($data[$field] ?? null)) {
                $data[$field] = trim($data[$field]);
            }
        }

        return Validator::make($data, [
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['required', 'string', 'min:10', 'max:10000'],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
        ], [], ['title' => 'título', 'description' => 'descripción', 'priority' => 'prioridad'])->validate();
    }
}
