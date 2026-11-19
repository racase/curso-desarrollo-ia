<?php

namespace Tests\Feature;

use App\Actions\Tickets\CreateTicket;
use App\Actions\Tickets\UpdateTicket;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Resources\Tickets\Pages\CreateTicket as CreateTicketPage;
use App\Filament\Resources\Tickets\Pages\EditTicket;
use App\Filament\Resources\Tickets\Pages\ListTickets;
use App\Filament\Resources\Tickets\Pages\ViewTicket;
use App\Models\Asset;
use App\Models\Ticket;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TicketClassificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Sleep::fake();
        config(['services.openrouter.api_key' => 'test-only-secret']);
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['title' => 'Impresora averiada', 'description' => 'La impresora no responde.'], $overrides);
    }

    private function response(): array
    {
        return ['choices' => [['finish_reason' => 'stop', 'message' => ['content' => '{"category":"hardware","urgency":"high"}']]]];
    }

    public function test_creation_ignores_client_classification_and_calls_outside_its_transaction(): void
    {
        $actor = User::factory()->create();
        $transactionLevel = DB::transactionLevel();
        Http::fake(function () use ($transactionLevel) {
            $this->assertSame($transactionLevel, DB::transactionLevel());

            return Http::response($this->response());
        });
        $ticket = app(CreateTicket::class)->handle($actor, $this->data(['category' => 'software', 'priority' => 'critical', 'urgency' => 'critical', 'status' => 'closed']));
        $this->assertSame(TicketCategory::Hardware, $ticket->category);
        $this->assertSame(TicketPriority::High, $ticket->priority);
        $this->assertSame(TicketStatus::Proposed, $ticket->status);
        $this->assertSame($actor->id, $ticket->requester_id);
        $this->assertNull($ticket->technician_id);
        $this->assertDatabaseCount('tickets', 1);
        Http::assertSentCount(1);
    }

    public function test_three_failures_create_one_unclassified_ticket_without_extra_transactions(): void
    {
        $transactionLevel = DB::transactionLevel();
        Http::fake(function () use ($transactionLevel) {
            $this->assertSame($transactionLevel, DB::transactionLevel());

            return Http::response(['error' => 'unavailable'], 503);
        });
        Sleep::whenFakingSleep(function () use ($transactionLevel) {
            $this->assertSame($transactionLevel, DB::transactionLevel());
        });
        $ticket = app(CreateTicket::class)->handle(User::factory()->create(), $this->data());
        $this->assertNull($ticket->category);
        $this->assertNull($ticket->priority);
        $this->assertSame(TicketStatus::Proposed, $ticket->status);
        $this->assertDatabaseCount('tickets', 1);
        Http::assertSentCount(3);
    }

    public function test_invalid_input_and_denied_creation_do_not_call_provider(): void
    {
        Http::fake();
        $actor = User::factory()->create();
        foreach ([['title' => ' '], ['description' => 'short'], ['asset_id' => 999]] as $invalid) {
            try {
                app(CreateTicket::class)->handle($actor, $this->data($invalid));
                $this->fail('Invalid data accepted');
            } catch (ValidationException) {
                $this->assertDatabaseCount('tickets', 0);
            }
        }
        Gate::before(fn ($user, $ability) => $ability === 'create' ? false : null);
        try {
            app(CreateTicket::class)->handle($actor, $this->data());
            $this->fail('Unauthorized creation');
        } catch (AuthorizationException) {
            Http::assertNothingSent();
        }
    }

    public function test_asset_disappearing_during_classification_prevents_partial_creation(): void
    {
        $asset = Asset::factory()->create();
        Http::fake(function () use ($asset) {
            $asset->delete();

            return Http::response($this->response());
        });
        try {
            app(CreateTicket::class)->handle(User::factory()->create(), $this->data(['asset_id' => $asset->id]));
            $this->fail('Missing asset accepted');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('asset_id', $exception->errors());
            $this->assertDatabaseCount('tickets', 0);
            Http::assertSentCount(1);
        }
    }

    public function test_persistence_failure_does_not_restart_classification(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response($this->response())]);
        Ticket::creating(fn () => throw new \RuntimeException('Persistence failed'));
        try {
            app(CreateTicket::class)->handle(User::factory()->create(), $this->data());
            $this->fail('Persistence error hidden');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Persistence failed', $exception->getMessage());
            Http::assertSentCount(1);
            $this->assertDatabaseCount('tickets', 0);
        } finally {
            Ticket::flushEventListeners();
        }
    }

    public function test_staff_can_correct_and_classify_without_calls(): void
    {
        Http::fake();
        foreach ([User::factory()->technician()->create(), User::factory()->admin()->create()] as $actor) {
            $ticket = Ticket::factory()->unclassified()->create();
            $updated = app(UpdateTicket::class)->handle($actor, $ticket, $this->data(['category' => 'software', 'priority' => 'low']));
            $this->assertTrue($updated->isClassified());
            $this->assertSame(TicketCategory::Software, $updated->category);
            $updated = app(UpdateTicket::class)->handle($actor, $updated, $this->data(['category' => 'network', 'priority' => 'critical']));
            $this->assertSame(TicketPriority::Critical, $updated->priority);
        }
        Http::assertNothingSent();
    }

    public function test_partial_classification_is_rejected_and_history_is_preserved(): void
    {
        Http::fake();
        $actor = User::factory()->technician()->create();
        $ticket = Ticket::factory()->create(['priority' => 'high']);
        $updated = app(UpdateTicket::class)->handle($actor, $ticket, $this->data());
        $this->assertNull($updated->category);
        $this->assertSame(TicketPriority::High, $updated->priority);
        foreach ([['category' => 'hardware'], ['priority' => 'low'], ['category' => 'wrong', 'priority' => 'low']] as $partial) {
            try {
                app(UpdateTicket::class)->handle($actor, $ticket, $this->data($partial));
                $this->fail('Partial classification accepted');
            } catch (ValidationException) {
                $this->assertNull($ticket->refresh()->category);
                $this->assertSame(TicketPriority::High, $ticket->priority);
            }
        }
        $complete = Ticket::factory()->classified()->create();
        try {
            app(UpdateTicket::class)->handle($actor, $complete, $this->data(['category' => null, 'priority' => null]));
            $this->fail('Complete classification cleared');
        } catch (ValidationException) {
            $this->assertTrue($complete->refresh()->isClassified());
        }
        Http::assertNothingSent();
    }

    public function test_requester_and_closed_records_cannot_change_classification(): void
    {
        Http::fake();
        $ticket = Ticket::factory()->classified()->create();
        $original = $ticket->title;
        foreach ([$ticket->requester, User::factory()->admin()->create()] as $actor) {
            if ($actor->id !== $ticket->requester_id) {
                $ticket->forceFill(['status' => 'closed'])->save();
            }
            try {
                app(UpdateTicket::class)->handle($actor, $ticket, $this->data(['category' => 'software', 'priority' => 'high']));
                $this->fail('Unauthorized classification accepted');
            } catch (AuthorizationException) {
                $this->assertSame($original, $ticket->refresh()->title);
                $this->assertSame(TicketCategory::Hardware, $ticket->category);
            }
        }
        Http::assertNothingSent();
    }

    public function test_filament_creation_success_failure_and_manual_correction(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner);
        $transactionLevel = DB::transactionLevel();
        Http::fake(function () use ($transactionLevel) {
            $this->assertSame($transactionLevel, DB::transactionLevel());

            return Http::response($this->response());
        });
        Livewire::test(CreateTicketPage::class)->assertFormFieldIsHidden('category')->assertFormFieldIsHidden('priority')
            ->fillForm($this->data())->call('create')->assertHasNoFormErrors()->assertDontSee('test-only-secret');
        $ticket = Ticket::sole();
        $this->assertTrue($ticket->isClassified());
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])->assertSee('Hardware')->assertSee('Alta')->assertDontSee('test-only-secret');
        Http::assertSentCount(1);

        $this->actingAs(User::factory()->technician()->create());
        Livewire::test(EditTicket::class, ['record' => $ticket->id])->assertFormFieldIsVisible('category')
            ->fillForm(['category' => 'software', 'priority' => 'medium'])->call('save')->assertHasNoFormErrors();
        $this->assertSame(TicketCategory::Software, $ticket->refresh()->category);
        Livewire::test(EditTicket::class, ['record' => $ticket->id])->fillForm(['description' => 'Descripción corregida por el técnico.'])
            ->call('save')->assertHasNoFormErrors();
        Http::assertSentCount(1);
    }

    public function test_filament_unclassified_and_legacy_views_and_partial_edit(): void
    {
        Http::fake();
        $actor = User::factory()->technician()->create();
        $this->actingAs($actor);
        $ticket = Ticket::factory()->unclassified()->create();
        $legacy = Ticket::factory()->create(['priority' => 'high']);
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])->assertSee('Sin clasificar');
        Livewire::test(ListTickets::class)->assertCanSeeTableRecords([$ticket, $legacy])->assertSee('Sin clasificar')
            ->filterTable('priority', 'high')->assertCanSeeTableRecords([$legacy])->assertCanNotSeeTableRecords([$ticket]);
        Livewire::test(EditTicket::class, ['record' => $ticket->id])->fillForm(['category' => 'software'])->call('save')
            ->assertHasFormErrors(['priority']);
        Livewire::test(EditTicket::class, ['record' => $legacy->id])->fillForm(['title' => 'Título histórico corregido'])->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame(TicketPriority::High, $legacy->refresh()->priority);
        Livewire::test(EditTicket::class, ['record' => $ticket->id])->fillForm(['category' => 'access', 'priority' => 'low'])
            ->call('save')->assertHasNoFormErrors()->assertDontSee('test-only-secret');
        Livewire::test(ViewTicket::class, ['record' => $ticket->id])->assertDontSee('Sin clasificar')->assertSee('Accesos');
        Http::assertNothingSent();
    }

    public function test_filament_rejects_tampered_classification_and_keeps_basic_editing(): void
    {
        Http::fake();
        $ticket = Ticket::factory()->classified()->create();
        $this->actingAs($ticket->requester);
        Livewire::test(EditTicket::class, ['record' => $ticket->id])->assertFormFieldIsHidden('category')
            ->fillForm(['title' => 'Título del solicitante'])->call('save')->assertHasNoFormErrors();
        Livewire::test(EditTicket::class, ['record' => $ticket->id])->set('data.priority', 'critical')->call('save')->assertForbidden();
        $this->assertSame(TicketPriority::Medium, $ticket->refresh()->priority);
        Http::assertNothingSent();
    }
}
