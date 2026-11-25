<?php

namespace App\Services;

use App\Enums\IncidentCategory;
use App\Enums\IncidentPriority;
use stdClass;
use UnexpectedValueException;

class IncidentClassificationSchema
{
    public static function definition(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'category' => [
                    'type' => 'string',
                    'enum' => array_values(array_diff(array_column(IncidentCategory::cases(), 'value'), ['unclassified'])),
                ],
                'priority' => [
                    'type' => 'string',
                    'enum' => array_values(array_diff(array_column(IncidentPriority::cases(), 'value'), ['unclassified'])),
                ],
            ],
            'required' => ['category', 'priority'],
            'additionalProperties' => false,
        ];
    }

    public static function validate(string $json): array
    {
        $value = json_decode($json, flags: JSON_THROW_ON_ERROR);
        $schema = self::definition();

        if (! $value instanceof stdClass) {
            throw new UnexpectedValueException('Classification must be a JSON object.');
        }

        $data = (array) $value;
        if (array_diff($schema['required'], array_keys($data)) || array_diff(array_keys($data), array_keys($schema['properties']))) {
            throw new UnexpectedValueException('Classification properties do not match the schema.');
        }

        foreach ($schema['properties'] as $name => $property) {
            if (! is_string($data[$name]) || ! in_array($data[$name], $property['enum'], true)) {
                throw new UnexpectedValueException('Classification value does not match the schema.');
            }
        }

        return $data;
    }
}
