<?php

namespace App\Services\Tickets;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use JsonException;
use Opis\JsonSchema\Validator;

class TicketClassificationSchema
{
    public function definition(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'properties' => [
                'category' => ['type' => 'string', 'enum' => array_column(TicketCategory::cases(), 'value')],
                'urgency' => ['type' => 'string', 'enum' => array_column(TicketPriority::cases(), 'value')],
            ],
            'required' => ['category', 'urgency'],
            'additionalProperties' => false,
        ];
    }

    public function parse(string $content): ?TicketClassification
    {
        try {
            $data = json_decode($content, false, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        $schema = json_decode(json_encode($this->definition(), JSON_THROW_ON_ERROR));
        if (! (new Validator)->validate($data, $schema)->isValid()) {
            return null;
        }

        return new TicketClassification(TicketCategory::from($data->category), TicketPriority::from($data->urgency));
    }
}
