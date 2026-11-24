<?php

namespace Tests\Feature;

use App\Actions\Incidents\AssignIncident;
use App\Actions\Incidents\CreateIncident;
use App\Actions\Incidents\TransitionIncident;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentStatusHistory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IncidentTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_assigns_and_reassigns_and_technician_self_assigns_free_incidents(): void
    {
        $admin = User::factory()->admin()->create();
        $technician = User::factory()->technician()->create();
        $second = User::factory()->technician()->create();
        $incident = Incident::factory()->create();
        $assigned = app(AssignIncident::class)->handle($technician, $incident, $technician->id);
        $this->assertSame($technician->id, $assigned->technician_id);
        $reassigned = app(AssignIncident::class)->handle($admin, $assigned, $second->id);
        $this->assertSame($second->id, $reassigned->technician_id);
        $this->assertSame(IncidentStatus::Proposed, $reassigned->status);
        $this->assertSame(0, $incident->statusHistory()->count());
        foreach (IncidentStatus::cases() as $status) {
            $incident->update(['status' => $status]);
            $this->assertSame($status, app(AssignIncident::class)->handle($admin, $incident, $technician->id)->status);
        }
    }

    public function test_invalid_assignment_targets_and_actors_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $technician = User::factory()->technician()->create();
        $requester = User::factory()->create();
        foreach ([$admin->id, $requester->id, 999999] as $invalidId) {
            $incident = Incident::factory()->create();
            try {
                app(AssignIncident::class)->handle($admin, $incident, $invalidId);
                $this->fail('Invalid target accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('technician_id', $exception->errors());
            }
            $this->assertNull($incident->fresh()->technician_id);
        }
        foreach ([$requester, $technician] as $actor) {
            $incident = Incident::factory()->assigned()->create();
            try {
                app(AssignIncident::class)->handle($actor, $incident, $actor->id);
                $this->fail('Unauthorized assignment accepted');
            } catch (AuthorizationException $exception) {
                $this->assertNotNull($exception);
            }
        }
        try {
            app(AssignIncident::class)->handle($technician, Incident::factory()->create(), User::factory()->technician()->create()->id);
            $this->fail('Technician assigned another user');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_all_status_pairs_enforce_the_sequential_map(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (IncidentStatus::cases() as $from) {
            foreach (IncidentStatus::cases() as $to) {
                $incident = Incident::factory()->assigned()->create(['status' => $from]);
                if ($from->next() === $to) {
                    $result = app(TransitionIncident::class)->handle($admin, $incident, $from, $to);
                    $this->assertSame($to, $result->status);
                    $entry = $result->statusHistory()->sole();
                    $this->assertSame($from, $entry->from_status);
                    $this->assertSame($to, $entry->to_status);
                    $this->assertSame($admin->id, $entry->actor_id);
                    $this->assertNotNull($entry->created_at);
                    $this->assertTrue($entry->actor->incidentStatusHistory->contains($entry));
                    $this->assertTrue($entry->incident->is($incident));
                } else {
                    try {
                        app(TransitionIncident::class)->handle($admin, $incident, $from, $to);
                        $this->fail('Invalid transition accepted');
                    } catch (ValidationException $exception) {
                        $this->assertArrayHasKey('status', $exception->errors());
                    }
                    $this->assertSame($from, $incident->fresh()->status);
                    $this->assertSame(0, $incident->statusHistory()->count());
                }
            }
        }
    }

    public function test_transition_permissions_for_each_role_and_assignment(): void
    {
        foreach (IncidentStatus::cases() as $from) {
            if ($from->next() === null) {
                continue;
            }
            foreach (['admin', 'assigned', 'other', 'requester'] as $role) {
                $incident = Incident::factory()->assigned()->create(['status' => $from]);
                $actor = match ($role) {
                    'admin' => User::factory()->admin()->create(),
                    'assigned' => $incident->technician,
                    'other' => User::factory()->technician()->create(),
                    'requester' => $incident->requester,
                };
                if (in_array($role, ['admin', 'assigned'])) {
                    $this->assertSame($from->next(), app(TransitionIncident::class)->handle($actor, $incident, $from, $from->next())->status);
                } else {
                    try {
                        app(TransitionIncident::class)->handle($actor, $incident, $from, $from->next());
                        $this->fail('Unauthorized transition accepted');
                    } catch (AuthorizationException $exception) {
                        $this->assertNotNull($exception);
                    }
                    $this->assertSame($from, $incident->fresh()->status);
                }
            }
        }
    }

    public function test_opening_needs_no_technician_but_starting_work_does(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = app(CreateIncident::class)->handle($admin, ['title' => 'Equipo', 'description' => 'No arranca', 'asset_id' => \App\Models\Asset::factory()->create()->id]);
        $this->assertSame(0, $incident->statusHistory()->count());
        $opened = app(TransitionIncident::class)->handle($admin, $incident, IncidentStatus::Proposed, IncidentStatus::Open);
        $this->assertNull($opened->technician_id);
        try {
            app(TransitionIncident::class)->handle($admin, $opened, IncidentStatus::Open, IncidentStatus::InProgress);
            $this->fail('Started without technician');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame(1, $incident->statusHistory()->count());
    }

    public function test_stale_transition_and_assignment_do_not_overwrite_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = Incident::factory()->create();
        app(TransitionIncident::class)->handle($admin, $incident, IncidentStatus::Proposed, IncidentStatus::Open);
        try {
            app(TransitionIncident::class)->handle($admin, $incident, IncidentStatus::Proposed, IncidentStatus::Open);
            $this->fail('Stale transition accepted');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('ha cambiado', $exception->getMessage());
        }
        $this->assertSame(1, $incident->statusHistory()->count());
        $first = User::factory()->technician()->create();
        $second = User::factory()->technician()->create();
        app(AssignIncident::class)->handle($first, $incident, $first->id);
        try {
            app(AssignIncident::class)->handle($second, $incident, $second->id);
            $this->fail('Stale autoassignment accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('technician_id', $exception->errors());
        }
        $this->assertSame($first->id, $incident->fresh()->technician_id);
    }

    public function test_history_failure_rolls_back_status_change(): void
    {
        $incident = Incident::factory()->create();
        $event = 'eloquent.creating: '.IncidentStatusHistory::class;
        Event::listen($event, fn () => throw new RuntimeException('Simulated history failure'));
        try {
            app(TransitionIncident::class)->handle(User::factory()->admin()->create(), $incident, IncidentStatus::Proposed, IncidentStatus::Open);
            $this->fail('Expected history failure');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated history failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertSame(IncidentStatus::Proposed, $incident->fresh()->status);
        $this->assertSame(0, $incident->statusHistory()->count());
    }
}
