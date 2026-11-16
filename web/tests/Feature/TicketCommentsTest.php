<?php

namespace Tests\Feature;

use App\Actions\Tickets\AddTicketComment;
use App\Enums\TicketStatus;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Resources\Tickets\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\Tickets\RelationManagers\StatusChangesRelationManager;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TicketCommentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_allowed_users_can_comment_without_assignment_using_their_identity(): void
    {
        $ticket = Ticket::factory()->create();
        foreach ([$ticket->requester, User::factory()->technician()->create(), User::factory()->admin()->create()] as $actor) {
            $comment = app(AddTicketComment::class)->handle($actor, $ticket, ['body' => '  Aclaración  ', 'author_id' => 999, 'created_at' => '2000-01-01']);
            $this->assertSame($actor->id, $comment->author->id);
            $this->assertSame('Aclaración', $comment->body);
            $this->assertSame($ticket->id, $comment->ticket->id);
            $this->assertTrue($comment->created_at->isToday());
        }
    }

    public function test_empty_and_oversized_comments_are_rejected(): void
    {
        $ticket = Ticket::factory()->create();
        foreach (['', '   ', str_repeat('a', 5001)] as $body) {
            try {
                app(AddTicketComment::class)->handle($ticket->requester, $ticket, ['body' => $body]);
                $this->fail('Invalid comment accepted');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('body', $exception->errors());
            }
        }
        foreach (['a', str_repeat('b', 5000)] as $body) {
            $this->assertSame($body, app(AddTicketComment::class)->handle($ticket->requester, $ticket, ['body' => $body])->body);
        }
    }

    public function test_unrelated_requester_cannot_post(): void
    {
        $this->expectException(AuthorizationException::class);
        app(AddTicketComment::class)->handle(User::factory()->create(), Ticket::factory()->create(), ['body' => 'Acceso indebido']);
    }

    public function test_closed_state_is_reloaded_before_publishing(): void
    {
        $ticket = Ticket::factory()->create();
        Ticket::whereKey($ticket->id)->update(['status' => 'closed']);
        $this->expectException(AuthorizationException::class);
        app(AddTicketComment::class)->handle($ticket->requester, $ticket, ['body' => 'No debe publicarse']);
    }

    public function test_conversation_is_paginated_ordered_and_escaped(): void
    {
        $ticket = Ticket::factory()->create();
        $this->actingAs($ticket->requester);
        $body = '<script>alert("xss")</script>';
        $first = TicketComment::factory()->create(['ticket_id' => $ticket->id, 'body' => $body]);
        $second = TicketComment::factory()->create(['ticket_id' => $ticket->id, 'created_at' => $first->created_at]);
        $this->assertSame([$first->id, $second->id], $ticket->comments()->pluck('id')->all());
        Livewire::test(CommentsRelationManager::class, ['ownerRecord' => $ticket, 'pageClass' => ViewTicket::class])
            ->assertCanSeeTableRecords([$first, $second], inOrder: true)
            ->assertSee($body)->assertDontSee($body, escape: false)
            ->callAction(TestAction::make('comment')->table(), ['body' => 'Nueva información'])
            ->assertHasNoActionErrors();
        $this->assertDatabaseHas('ticket_comments', ['body' => 'Nueva información', 'author_id' => $ticket->requester_id]);
        TicketComment::factory()->count(12)->create(['ticket_id' => $ticket->id]);
        Livewire::test(CommentsRelationManager::class, ['ownerRecord' => $ticket, 'pageClass' => ViewTicket::class])
            ->set('tableRecordsPerPage', 5)->assertCountTableRecords(15)->assertCanNotSeeTableRecords([$ticket->comments()->reorder()->latest('id')->first()]);
    }

    public function test_direct_relation_access_cannot_bypass_parent_visibility(): void
    {
        $this->actingAs(User::factory()->create());
        $ticket = Ticket::factory()->create();
        foreach ([CommentsRelationManager::class, StatusChangesRelationManager::class] as $manager) {
            Livewire::test($manager, ['ownerRecord' => $ticket, 'pageClass' => ViewTicket::class])->assertForbidden();
        }
    }

    public function test_closed_conversation_is_read_only_and_keeps_comments(): void
    {
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Closed]);
        $comment = TicketComment::factory()->create(['ticket_id' => $ticket->id]);
        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(CommentsRelationManager::class, ['ownerRecord' => $ticket, 'pageClass' => ViewTicket::class])
            ->assertCanSeeTableRecords([$comment])
            ->assertActionHidden(TestAction::make('comment')->table())
            ->assertActionDoesNotExist(TestAction::make('edit')->table($comment))
            ->assertActionDoesNotExist(TestAction::make('delete')->table($comment));
        $this->assertDatabaseCount('ticket_comments', 1);
    }

    public function test_empty_comment_errors_are_shown_on_the_action_field(): void
    {
        $ticket = Ticket::factory()->create();
        $this->actingAs($ticket->requester);
        Livewire::test(CommentsRelationManager::class, ['ownerRecord' => $ticket, 'pageClass' => ViewTicket::class])
            ->callAction(TestAction::make('comment')->table(), ['body' => '   '])
            ->assertHasActionErrors(['body']);
        $this->assertDatabaseCount('ticket_comments', 0);
    }
}
