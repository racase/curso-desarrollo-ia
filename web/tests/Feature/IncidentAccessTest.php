<?php

namespace Tests\Feature;

use App\Actions\Incidents\CreateIncident;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Enums\UserRole;
use App\Models\Asset;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IncidentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_creates_only_for_itself_with_protected_defaults(): void
    {
        foreach (UserRole::cases() as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $incident = app(CreateIncident::class)->handle($actor, [
                'asset_id' => Asset::factory()->create()->id,
                'title' => '  Sin conexión  ', 'description' => '  Revisar el equipo  ',
                'requester_id' => 9999, 'technician_id' => 9999, 'status' => 'closed',
            ])->fresh();
            $this->assertSame($actor->id, $incident->requester_id);
            $this->assertNull($incident->technician_id);
            $this->assertSame(IncidentStatus::Proposed, $incident->status);
            $this->assertSame(IncidentPriority::Medium, $incident->priority);
            $this->assertSame('Sin conexión', $incident->title);
            $this->assertSame('Revisar el equipo', $incident->description);
        }
    }

    public function test_explicit_priority_and_maximum_lengths_are_accepted(): void
    {
        $incident = app(CreateIncident::class)->handle(User::factory()->create(), [
            'asset_id' => Asset::factory()->create()->id,
            'title' => str_repeat('á', 255), 'description' => str_repeat('ñ', 10000), 'priority' => 'critical',
        ]);
        $this->assertSame(IncidentPriority::Critical, $incident->priority);
    }

    public function test_invalid_inputs_are_rejected_without_creating_incidents(): void
    {
        foreach ([['title' => ' '], ['title' => str_repeat('a', 256)], ['description' => ' '], ['description' => str_repeat('a', 10001)], ['priority' => 'invalid']] as $invalid) {
            try {
                app(CreateIncident::class)->handle(User::factory()->create(), [...['title' => 'Equipo', 'description' => 'No arranca', 'asset_id' => Asset::factory()->create()->id], ...$invalid]);
                $this->fail('Invalid incident accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey(array_key_first($invalid), $exception->errors());
            }
        }
        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_requesters_see_only_their_records_and_staff_see_all(): void
    {
        $own = Incident::factory()->create();
        $other = Incident::factory()->create();
        $this->assertSame([$own->id], Incident::visibleTo($own->requester)->pluck('id')->all());
        $this->assertFalse(Gate::forUser($own->requester)->allows('view', $other));
        foreach ([User::factory()->admin()->create(), User::factory()->technician()->create()] as $staff) {
            $this->assertSame(2, Incident::visibleTo($staff)->count());
            $this->assertTrue(Gate::forUser($staff)->allows('view', $other));
        }
        foreach (UserRole::cases() as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->assertFalse(Gate::forUser($actor)->allows('update', $own));
            $this->assertFalse(Gate::forUser($actor)->allows('delete', $own));
            $this->assertFalse(Gate::forUser($actor)->allows('deleteAny', Incident::class));
        }
    }
}
