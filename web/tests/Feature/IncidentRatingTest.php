<?php

namespace Tests\Feature;

use App\Actions\Incidents\AssignIncident;
use App\Actions\Incidents\RateIncident;
use App\Actions\Incidents\TransitionIncident;
use App\Actions\Users\DeleteUser;
use App\Actions\Users\UpdateUser;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\IncidentStatusHistory;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class IncidentRatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_score_is_saved_once_without_changing_the_incident(): void
    {
        foreach (range(1, 5) as $score) {
            $incident = Incident::factory()->closed()->create();
            $before = $incident->only(['status', 'technician_id', 'closing_technician_id']);
            $result = app(RateIncident::class)->handle($incident->requester, $incident, [
                'resolution_rating' => $score, 'closing_technician_id' => 999,
            ]);
            $this->assertSame($score, $result->resolution_rating);
            $this->assertNotNull($result->rated_at);
            $this->assertSame($before, $result->only(array_keys($before)));
            $this->assertSame(0, $result->statusHistory()->count());
            try {
                app(RateIncident::class)->handle($incident->requester, $incident, ['resolution_rating' => 1]);
                $this->fail('Second rating accepted');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('ya está valorada', $exception->getMessage());
                $this->assertSame($score, $incident->fresh()->resolution_rating);
            }
        }
    }

    public function test_invalid_scores_never_save_partial_data(): void
    {
        $incident = Incident::factory()->closed()->create();
        foreach ([null, '', 0, -1, 6, 1.5, 'incorrecto', [], true] as $score) {
            try {
                app(RateIncident::class)->handle($incident->requester, $incident, ['resolution_rating' => $score]);
                $this->fail('Invalid score accepted: '.json_encode($score));
            } catch (ValidationException) {
                $this->assertNull($incident->fresh()->resolution_rating);
                $this->assertNull($incident->fresh()->rated_at);
            }
        }
    }

    public function test_roles_ownership_status_and_historical_attribution_are_required(): void
    {
        $incident = Incident::factory()->closed()->create();
        foreach ([User::factory()->create(), User::factory()->admin()->create(), User::factory()->technician()->create()] as $actor) {
            $this->denies($actor, $incident);
            if ($actor->role->value !== 'requester') {
                $own = Incident::factory()->closed()->create(['requester_id' => $actor->id]);
                $this->denies($actor, $own);
            }
        }
        foreach (IncidentStatus::cases() as $status) {
            if ($status !== IncidentStatus::Closed) {
                $pending = Incident::factory()->create(['status' => $status]);
                $this->denies($pending->requester, $pending);
            }
        }
        $historical = Incident::factory()->historicalWithoutTechnician()->create();
        app(AssignIncident::class)->handle(User::factory()->admin()->create(), $historical, $incident->technician_id);
        $this->denies($historical->requester, $historical->fresh());
    }

    private function denies(User $actor, Incident $incident): void
    {
        try {
            app(RateIncident::class)->handle($actor, $incident, ['resolution_rating' => 4]);
            $this->fail('Unauthorized rating accepted');
        } catch (AuthorizationException) {
            $this->assertNull($incident->fresh()->resolution_rating);
        }
    }

    public function test_closure_captures_the_technician_and_rolls_back_on_history_failure(): void
    {
        $admin = User::factory()->admin()->create();
        $incident = Incident::factory()->assigned()->create(['status' => IncidentStatus::Resolved]);
        $event = 'eloquent.creating: '.IncidentStatusHistory::class;
        Event::listen($event, fn () => throw new RuntimeException('History failure'));
        try {
            app(TransitionIncident::class)->handle($admin, $incident, IncidentStatus::Resolved, IncidentStatus::Closed);
            $this->fail('Expected rollback');
        } catch (RuntimeException) {
            $this->assertSame(IncidentStatus::Resolved, $incident->fresh()->status);
            $this->assertNull($incident->fresh()->closing_technician_id);
        } finally {
            Event::forget($event);
        }
        $closed = app(TransitionIncident::class)->handle($admin, $incident, IncidentStatus::Resolved, IncidentStatus::Closed);
        $this->assertSame($closed->technician_id, $closed->closing_technician_id);
        $this->assertNull($closed->resolution_rating);
    }

    public function test_reassignment_and_role_change_preserve_attribution_and_protect_deletion(): void
    {
        $admin = User::factory()->admin()->create();
        foreach ([false, true] as $rated) {
            $incident = Incident::factory()->closed()->create();
            $technician = $incident->closingTechnician;
            $replacement = User::factory()->technician()->create();
            app(AssignIncident::class)->handle($admin, $incident, $replacement->id);
            if ($rated) {
                app(RateIncident::class)->handle($incident->requester, $incident, ['resolution_rating' => 5]);
                app(AssignIncident::class)->handle($admin, $incident, $replacement->id);
            }
            app(UpdateUser::class)->handle($admin, $technician, ['name' => $technician->name,
                'email' => $technician->email, 'role' => 'requester', 'password' => '']);
            $this->assertSame($technician->id, $incident->fresh()->closing_technician_id);
            try {
                app(DeleteUser::class)->handle($admin, $technician);
                $this->fail('Historical technician deleted');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('técnico del cierre', $exception->getMessage());
                $this->assertModelExists($technician);
            }
        }
    }

    public function test_migration_initializes_existing_rows_and_reverses_without_losing_incidents(): void
    {
        $migration = require database_path('migrations/2026_11_24_120000_add_resolution_rating_to_incidents_table.php');
        $migration->down();
        $closed = Incident::factory()->assigned()->create(['status' => 'closed']);
        $unassigned = Incident::factory()->create(['status' => 'closed']);
        $pending = Incident::factory()->assigned()->create(['status' => 'resolved']);
        $comment = IncidentComment::factory()->create(['incident_id' => $closed->id]);
        $history = IncidentStatusHistory::factory()->create(['incident_id' => $closed->id]);
        $updatedAt = $closed->updated_at;
        $migration->up();
        $this->assertModelExists($comment);
        $this->assertModelExists($history);
        $this->assertSame($closed->technician_id, $closed->fresh()->closing_technician_id);
        $this->assertTrue($closed->fresh()->updated_at->equalTo($updatedAt));
        $this->assertNull($closed->fresh()->resolution_rating);
        $this->assertNull($unassigned->fresh()->closing_technician_id);
        $this->assertNull($pending->fresh()->closing_technician_id);
        app(TransitionIncident::class)->handle(User::factory()->admin()->create(), $pending, IncidentStatus::Resolved, IncidentStatus::Closed);
        $this->assertSame($pending->technician_id, $pending->fresh()->closing_technician_id);
        $migration->down();
        $this->assertDatabaseCount('incidents', 3);
        $migration->up();
    }

    public function test_database_rejects_invalid_score_even_outside_the_action(): void
    {
        $incident = Incident::factory()->closed()->create();
        $this->expectException(QueryException::class);
        DB::table('incidents')->where('id', $incident->id)->update(['resolution_rating' => 6, 'rated_at' => now()]);
    }
}
