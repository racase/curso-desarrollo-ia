<?php

namespace Tests\Feature;

use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_statuses_and_priorities_persist_with_user_relations(): void
    {
        foreach (IncidentStatus::cases() as $status) {
            foreach (IncidentPriority::cases() as $priority) {
                $incident = Incident::factory()->assigned()->create(compact('status', 'priority'))->fresh();
                $this->assertSame($status, $incident->status);
                $this->assertSame($priority, $incident->priority);
                $this->assertTrue($incident->requester->requestedIncidents->contains($incident));
                $this->assertTrue($incident->technician->assignedIncidents->contains($incident));
            }
        }
    }

    public function test_requester_cannot_be_deleted_while_referenced(): void
    {
        $incident = Incident::factory()->create();
        $this->expectException(QueryException::class);
        $incident->requester->delete();
    }

    public function test_incident_requires_an_existing_requester(): void
    {
        $this->expectException(QueryException::class);
        Incident::factory()->create(['requester_id' => 999999]);
    }
}
