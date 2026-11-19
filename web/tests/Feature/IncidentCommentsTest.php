<?php

namespace Tests\Feature;

use App\Actions\Incidents\CommentOnIncident;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IncidentCommentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_authorized_role_can_comment_in_every_status_and_cannot_forge_attribution(): void
    {
        $this->travelTo(now()->startOfSecond());
        foreach (IncidentStatus::cases() as $status) {
            $incident = Incident::factory()->create(['status' => $status]);
            foreach ([$incident->requester, User::factory()->technician()->create(), User::factory()->admin()->create()] as $actor) {
                $comment = app(CommentOnIncident::class)->handle($actor, $incident, [
                    'body' => '  Aclaración  ', 'author_id' => 9999, 'incident_id' => 9999, 'created_at' => '2000-01-01',
                ]);
                $this->assertSame($actor->id, $comment->author_id);
                $this->assertSame($incident->id, $comment->incident_id);
                $this->assertSame('Aclaración', $comment->body);
                $this->assertTrue($comment->created_at->equalTo(now()));
                $this->assertTrue($actor->incidentComments->contains($comment));
                $this->assertTrue(Gate::forUser($actor)->allows('view', $comment));
                $this->assertFalse(Gate::forUser($actor)->allows('update', $comment));
                $this->assertFalse(Gate::forUser($actor)->allows('delete', $comment));
            }
        }
    }

    public function test_outsider_cannot_read_or_publish_comments(): void
    {
        $comment = IncidentComment::factory()->create();
        $outsider = User::factory()->create();
        $this->assertFalse(Gate::forUser($outsider)->allows('view', $comment));
        $this->expectException(AuthorizationException::class);
        app(CommentOnIncident::class)->handle($outsider, $comment->incident, ['body' => 'No autorizado']);
    }

    public function test_comment_limits_and_whitespace_are_validated(): void
    {
        $incident = Incident::factory()->create();
        foreach (['', '  ', str_repeat('a', 5001)] as $body) {
            try {
                app(CommentOnIncident::class)->handle($incident->requester, $incident, compact('body'));
                $this->fail('Invalid comment accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('body', $exception->errors());
            }
        }
        $this->assertDatabaseCount('incident_comments', 0);
        $comment = app(CommentOnIncident::class)->handle($incident->requester, $incident, ['body' => str_repeat('ñ', 5000)]);
        $this->assertSame(5000, mb_strlen($comment->body));
    }

    public function test_comments_are_ordered_by_date_then_id_and_attribution_is_preserved(): void
    {
        $incident = Incident::factory()->create();
        $later = IncidentComment::factory()->create(['incident_id' => $incident->id, 'created_at' => now()->addMinute()]);
        $first = IncidentComment::factory()->create(['incident_id' => $incident->id, 'created_at' => now()]);
        $second = IncidentComment::factory()->create(['incident_id' => $incident->id, 'created_at' => $first->created_at]);
        $this->assertSame([$first->id, $second->id, $later->id], $incident->comments->modelKeys());
        $this->assertTrue($first->incident->is($incident));
        $this->assertTrue($first->author->incidentComments->contains($first));
    }
}
