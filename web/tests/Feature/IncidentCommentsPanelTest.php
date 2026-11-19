<?php

namespace Tests\Feature;

use App\Filament\Resources\Incidents\Pages\ViewIncident;
use App\Filament\Resources\Incidents\RelationManagers\CommentsRelationManager;
use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IncidentCommentsPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_conversation_can_receive_a_comment_and_displays_escaped_text(): void
    {
        $incident = Incident::factory()->create(['status' => 'closed']);
        $this->actingAs($incident->requester);
        Livewire::test(CommentsRelationManager::class, ['ownerRecord' => $incident, 'pageClass' => ViewIncident::class])
            ->assertSee('Todavía no hay comentarios')
            ->callAction(TestAction::make('comment')->table(), ['body' => '<script>alert(1)</script>'])
            ->assertHasNoActionErrors()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee($incident->requester->name);
        $this->assertDatabaseCount('incident_comments', 1);
    }

    public function test_conversation_has_stable_pagination_and_no_mutating_actions(): void
    {
        $incident = Incident::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $comments = IncidentComment::factory()->count(12)->create(['incident_id' => $incident->id, 'created_at' => now()]);
        Livewire::test(CommentsRelationManager::class, ['ownerRecord' => $incident, 'pageClass' => ViewIncident::class])
            ->assertCanSeeTableRecords($comments->take(10), inOrder: true)
            ->assertCanNotSeeTableRecords($comments->skip(10))
            ->assertActionDoesNotExist(TestAction::make('edit')->table($comments->first()))
            ->assertActionDoesNotExist(TestAction::make('delete')->table($comments->first()))
            ->call('gotoPage', 2, 'commentsRelationManagerPage')
            ->assertCanSeeTableRecords($comments->skip(10), inOrder: true);
    }

    public function test_outsider_cannot_mount_comment_manager_or_publish(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(CommentsRelationManager::class, ['ownerRecord' => Incident::factory()->create(), 'pageClass' => ViewIncident::class])->assertForbidden();
        $this->assertDatabaseCount('incident_comments', 0);
    }

    public function test_invalid_comment_shows_field_error(): void
    {
        $incident = Incident::factory()->create();
        $this->actingAs($incident->requester);
        Livewire::test(CommentsRelationManager::class, ['ownerRecord' => $incident, 'pageClass' => ViewIncident::class])
            ->callAction(TestAction::make('comment')->table(), ['body' => ' '])->assertHasActionErrors(['body']);
        $this->assertDatabaseCount('incident_comments', 0);
    }
}
