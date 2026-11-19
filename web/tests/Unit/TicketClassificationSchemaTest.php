<?php

namespace Tests\Unit;

use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Services\Tickets\TicketClassificationSchema;
use PHPUnit\Framework\TestCase;

class TicketClassificationSchemaTest extends TestCase
{
    public function test_schema_accepts_every_catalog_combination(): void
    {
        $schema = new TicketClassificationSchema;
        foreach (TicketCategory::cases() as $category) {
            foreach (TicketPriority::cases() as $priority) {
                $result = $schema->parse(json_encode(['category' => $category->value, 'urgency' => $priority->value]));
                $this->assertSame($category, $result?->category);
                $this->assertSame($priority, $result?->priority);
            }
        }
    }

    public function test_schema_rejects_invalid_json_shapes_and_values(): void
    {
        foreach (['', 'null', '[]', '{}', 'false', '"text"', '["hardware", "high"]', '{"category":"hardware"}', '{"category":null,"urgency":"low"}', '{"category":1,"urgency":"high"}', '{"category":"unknown","urgency":"low"}', '{"category":"hardware","urgency":"urgent"}', '{"category":"hardware","urgency":"low","extra":true}', '```json {"category":"hardware","urgency":"low"} ```', '{"category":"hardware","urgency":"low"} trailing', '{"category":"hardware","urgency":null}'] as $content) {
            $this->assertNull((new TicketClassificationSchema)->parse($content), $content);
        }
    }
}
