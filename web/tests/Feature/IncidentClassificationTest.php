<?php

namespace Tests\Feature;

use App\Actions\Incidents\CorrectIncidentClassification;
use App\Actions\Incidents\CreateIncident;
use App\Enums\IncidentCategory;
use App\Enums\IncidentPriority;
use App\Enums\IncidentStatus;
use App\Filament\Resources\Incidents\Pages\CreateIncident as CreateIncidentPage;
use App\Filament\Resources\Incidents\Pages\ViewIncident;
use App\Models\Asset;
use App\Models\Incident;
use App\Models\User;
use App\Services\IncidentClassificationSchema;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class IncidentClassificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.api_key' => 'test-secret', 'services.openrouter.model' => 'gpt-oss120b']);
    }

    private static function completion(mixed $content = '{"category":"network","priority":"high"}', string $finish = 'stop'): array
    {
        return ['choices' => [['message' => ['content' => $content], 'finish_reason' => $finish]]];
    }

    private function createIncident(array $overrides = []): Incident
    {
        return app(CreateIncident::class)->handle(User::factory()->create(), [
            'title' => 'Sin conexión', 'description' => 'Toda la oficina está sin red.',
            'asset_id' => Asset::factory()->create()->id,
            ...$overrides,
        ])->fresh();
    }

    public function test_creation_calls_openrouter_with_schema_and_persists_only_valid_classification(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response(self::completion())]);
        $incident = $this->createIncident(['category' => 'hardware', 'priority' => 'low']);
        $this->assertSame(IncidentCategory::Network, $incident->category);
        $this->assertSame(IncidentPriority::High, $incident->priority);
        $this->assertSame(IncidentStatus::Proposed, $incident->status);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-secret')
            && $request['model'] === 'openai/gpt-oss-120b'
            && $request['messages'][1] === ['role' => 'user', 'content' => $incident->description]
            && $request['provider']['require_parameters'] === true
            && $request['response_format']['type'] === 'json_schema'
            && $request['response_format']['json_schema']['strict'] === true
            && $request['response_format']['json_schema']['schema'] === IncidentClassificationSchema::definition());
    }

    public function test_two_failures_then_success_uses_exactly_three_attempts(): void
    {
        Http::fake(['openrouter.ai/*' => Http::sequence()
            ->pushStatus(503)->push(self::completion('not json'))->push(self::completion())]);
        $incident = $this->createIncident();
        $this->assertSame(IncidentCategory::Network, $incident->category);
        Http::assertSentCount(3);
    }

    public function test_second_attempt_success_stops_retrying(): void
    {
        Http::fake(['openrouter.ai/*' => Http::sequence()->pushStatus(429)->push(self::completion())]);
        $this->assertSame(IncidentPriority::High, $this->createIncident()->priority);
        Http::assertSentCount(2);
    }

    #[DataProvider('invalidResponses')]
    public function test_invalid_or_failed_responses_exhaust_attempts_and_save_unclassified(array $body, int $status): void
    {
        Http::fake(['openrouter.ai/*' => Http::response($body, $status)]);
        $incident = $this->createIncident();
        $this->assertSame(IncidentCategory::Unclassified, $incident->category);
        $this->assertSame(IncidentPriority::Unclassified, $incident->priority);
        $this->assertSame(IncidentStatus::Proposed, $incident->status);
        $this->assertDatabaseCount('incidents', 1);
        Http::assertSentCount(3);
    }

    public static function invalidResponses(): array
    {
        return [
            'server error' => [[], 503],
            'authentication' => [[], 401],
            'rate limit' => [[], 429],
            'embedded error' => [['error' => ['message' => 'Provider failed']], 200],
            'invalid JSON' => [self::completion('not json'), 200],
            'fenced JSON' => [self::completion('```json {"category":"network","priority":"high"} ```'), 200],
            'missing priority' => [self::completion('{"category":"network"}'), 200],
            'unknown category' => [self::completion('{"category":"invented","priority":"high"}'), 200],
            'unknown priority' => [self::completion('{"category":"network","priority":"urgent"}'), 200],
            'unclassified is not a proposal' => [self::completion('{"category":"unclassified","priority":"high"}'), 200],
            'extra property' => [self::completion('{"category":"network","priority":"high","status":"closed"}'), 200],
            'wrong type' => [self::completion('{"category":[],"priority":1}'), 200],
            'null' => [self::completion('null'), 200],
            'array' => [self::completion('[]'), 200],
            'non string content' => [self::completion(['category' => 'network']), 200],
            'truncated' => [self::completion(finish: 'length'), 200],
            'refused' => [self::completion(null, 'content_filter'), 200],
        ];
    }

    public function test_connection_failures_are_retried_and_do_not_prevent_creation(): void
    {
        $attempts = 0;
        Http::fake(function () use (&$attempts) {
            $attempts++;

            return Http::failedConnection();
        });
        $this->assertSame(IncidentCategory::Unclassified, $this->createIncident()->category);
        $this->assertSame(3, $attempts);
    }

    public function test_missing_key_does_not_send_a_request(): void
    {
        config(['services.openrouter.api_key' => '']);
        Http::fake();
        $this->assertSame(IncidentPriority::Unclassified, $this->createIncident()->priority);
        Http::assertNothingSent();
    }

    public function test_invalid_incident_is_rejected_before_calling_the_provider(): void
    {
        Http::fake();
        try {
            $this->createIncident(['description' => ' ']);
            $this->fail('Invalid incident accepted');
        } catch (ValidationException) {
            Http::assertNothingSent();
            $this->assertDatabaseCount('incidents', 0);
        }
    }

    public function test_filament_creation_displays_the_proposal_without_exposing_credentials(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response(self::completion())]);
        $this->actingAs(User::factory()->create());
        Livewire::test(CreateIncidentPage::class)
            ->assertFormFieldDoesNotExist('priority')->assertFormFieldDoesNotExist('category')
            ->fillForm(['title' => 'Sin conexión', 'description' => 'Sin red', 'asset_id' => Asset::factory()->create()->id])
            ->call('create')->assertHasNoFormErrors();
        Livewire::test(ViewIncident::class, ['record' => Incident::sole()->id])
            ->assertSee('Red')->assertSee('Alta')->assertDontSee('test-secret');
    }

    public function test_staff_can_correct_from_filament_without_reclassifying_or_overwriting_other_fields(): void
    {
        Http::fake();
        foreach ([User::factory()->technician()->create(), User::factory()->admin()->create()] as $staff) {
            $incident = Incident::factory()->create();
            $this->actingAs($staff);
            Livewire::test(ViewIncident::class, ['record' => $incident->id])->assertActionVisible('classify')
                ->callAction('classify', ['category' => 'software', 'priority' => 'critical'])->assertHasNoActionErrors();
            $this->assertSame(IncidentCategory::Software, $incident->fresh()->category);
            $this->assertSame(IncidentPriority::Critical, $incident->fresh()->priority);
            $incident->title = 'Stale title';
            $before = $incident->fresh()->getRawOriginal();
            $corrected = app(CorrectIncidentClassification::class)->handle($staff, $incident, [
                'category' => 'hardware', 'priority' => 'low', 'status' => 'closed', 'requester_id' => 9999,
            ]);
            foreach ($before as $key => $value) {
                if (! in_array($key, ['category', 'priority', 'updated_at'], true)) {
                    $this->assertSame($value, $corrected->getRawOriginal($key));
                }
            }
        }
        Http::assertNothingSent();
    }

    public function test_requester_cannot_correct_even_by_calling_the_action_directly(): void
    {
        $incident = Incident::factory()->create();
        $this->actingAs($incident->requester);
        Livewire::test(ViewIncident::class, ['record' => $incident->id])->assertActionHidden('classify')
            ->call('mountAction', 'classify')->assertSet('mountedActions', []);
        $this->expectException(AuthorizationException::class);
        app(CorrectIncidentClassification::class)->handle($incident->requester, $incident, ['category' => 'software', 'priority' => 'critical']);
    }

    public function test_manual_correction_rejects_invalid_values(): void
    {
        $incident = Incident::factory()->create();
        $staff = User::factory()->technician()->create();
        foreach ([['category' => 'invented', 'priority' => 'high'], ['category' => 'software', 'priority' => 'urgent'], []] as $input) {
            try {
                app(CorrectIncidentClassification::class)->handle($staff, $incident, $input);
                $this->fail('Invalid classification accepted');
            } catch (ValidationException) {
                $this->assertSame(IncidentCategory::Unclassified, $incident->fresh()->category);
            }
        }
    }
}
