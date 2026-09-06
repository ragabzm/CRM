<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Capabilities;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * One API, and the machines come in through the same door.
 *
 * The claim this story makes is not "there is an API" — there always was. It
 * is that there is only ONE: no second surface, no internal-only endpoint, no
 * privileged bypass. A machine and a person reach the same controller, the
 * same validation, the same capability gate and the same command, and the
 * tests below are mostly about proving the absence of the alternative.
 *
 * The other half is that a token NARROWS. An administrator who scopes a client
 * to reading tickets has scoped it, and a credential that quietly inherited
 * everything its service account holds would be the opposite of what they
 * pressed the button for.
 */
final class PublicApiTest extends TestCase
{
    use InteractsWithSpaSession;
    use MakesTickets;
    use RefreshDatabase;

    private User $administrator;

    private User $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Cache::clear();

        $this->administrator = $this->makeUser(Roles::ADMINISTRATOR);
        $this->service = $this->makeUser(Roles::AGENT);
    }

    private function settings(): SettingsRegistry
    {
        return $this->app->make(SettingsRegistry::class);
    }

    /** Issues a client the way an administrator does, and returns its token. */
    private function issue(array $abilities, ?User $owner = null): string
    {
        $response = $this->actingAs($this->administrator, 'web')
            ->withIdempotencyKey()
            ->postJson('/api/v1/api-clients', [
                'name' => 'Warehouse system',
                'owner_id' => ($owner ?? $this->service)->getKey(),
                'abilities' => $abilities,
            ]);

        $response->assertCreated();

        return (string) $response->json('data.token');
    }

    private function asClient(string $token): self
    {
        /*
         * The session is dropped first, and that is not tidying.
         *
         * `auth:web,sanctum` tries the COOKIE before the bearer header, which
         * is right — the interface is the common case. But `actingAs` in this
         * test persists across requests, so without this every call below
         * would authenticate as the administrator who issued the token and the
         * token would never be exercised at all.
         *
         * The first version of this file did exactly that: four tests failed
         * for one reason, and two of them — the rate limits — failed by
         * falling back to an IP key and sharing a budget. A machine calling
         * this API has no session, and the test has to be the machine.
         */
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token);

        return $this;
    }

    public function test_a_client_reads_tickets_through_the_same_endpoint_the_interface_uses(): void
    {
        $this->makeTicket();

        $token = $this->issue([Capabilities::TICKET_READ]);

        $throughTheApi = $this->asClient($token)->getJson('/api/v1/tickets');

        $throughTheApi->assertOk();

        /*
         * The SAME path, not an equivalent one. If a second API existed this
         * would be a different URL — and the two would drift the first time
         * somebody fixed a bug in one of them.
         */
        $this->assertSame(
            '/api/v1/tickets',
            parse_url((string) $throughTheApi->baseRequest->getUri(), PHP_URL_PATH),
        );
    }

    public function test_a_token_narrows_what_its_owner_can_do(): void
    {
        $this->makeTicket();

        // The service account is an agent and may create tickets. This client
        // may not: it was scoped to reading.
        $token = $this->issue([Capabilities::TICKET_READ]);

        $this->asClient($token)->getJson('/api/v1/tickets')->assertOk();

        $this->asClient($token)
            ->withHeader('Idempotency-Key', (string) \Illuminate\Support\Str::ulid())
            ->postJson('/api/v1/tickets', [])
            ->assertForbidden();
    }

    public function test_a_token_can_never_widen_what_its_owner_can_do(): void
    {
        /*
         * Refused at ISSUE. An agent cannot manage users, so a client of that
         * agent cannot be scoped to it — and an administrator who tried has
         * made a mistake they should hear about now rather than in a support
         * ticket about an integration that 403s on one endpoint.
         */
        $response = $this->actingAs($this->administrator, 'web')
            ->withIdempotencyKey()
            ->postJson('/api/v1/api-clients', [
                'name' => 'Too much',
                'owner_id' => $this->service->getKey(),
                'abilities' => [Capabilities::USER_MANAGE],
            ]);

        $response->assertStatus(422);
        $this->assertSame('security.ability_exceeds_owner', $response->json('code'));
    }

    public function test_an_ability_must_be_a_capability_not_an_endpoint(): void
    {
        $response = $this->actingAs($this->administrator, 'web')
            ->withIdempotencyKey()
            ->postJson('/api/v1/api-clients', [
                'name' => 'Endpoint-scoped',
                'owner_id' => $this->service->getKey(),
                // The shape somebody reaches for, and the one that becomes a
                // second permission model the moment a route is added.
                'abilities' => ['GET /api/v1/tickets'],
            ]);

        $response->assertStatus(422);
        $this->assertSame('security.unknown_ability', $response->json('code'));
    }

    public function test_a_client_with_no_abilities_is_refused(): void
    {
        $response = $this->actingAs($this->administrator, 'web')
            ->withIdempotencyKey()
            ->postJson('/api/v1/api-clients', [
                'name' => 'Useless',
                'owner_id' => $this->service->getKey(),
                'abilities' => [],
            ]);

        // A credential that fails every call for a reason nobody can see from
        // the outside.
        $response->assertStatus(422);
    }

    public function test_the_token_is_shown_once_and_never_again(): void
    {
        $token = $this->issue([Capabilities::TICKET_READ]);

        $this->assertNotSame('', $token);

        $list = $this->actingAs($this->administrator, 'web')->getJson('/api/v1/api-clients');

        $list->assertOk();

        /*
         * Not in the list, not behind a reveal control, not masked with the
         * last four showing. There is no code path that returns it a second
         * time, which is a stronger promise than a rule about who may ask.
         */
        $this->assertStringNotContainsString($token, $list->getContent());

        foreach ($list->json('data') as $client) {
            $this->assertArrayNotHasKey('token', $client);
        }
    }

    public function test_the_token_is_hashed_at_rest_and_absent_from_the_audit(): void
    {
        $token = $this->issue([Capabilities::TICKET_READ]);

        // Sanctum's plaintext is `id|secret`; the row stores a hash of the
        // secret half.
        [, $secret] = explode('|', $token, 2);

        $stored = (string) DB::table('personal_access_tokens')->value('token');

        $this->assertNotSame($secret, $stored);
        $this->assertSame(hash('sha256', $secret), $stored);

        $audit = DB::table('audit_entries')->where('action', 'api_client.issued')->first();

        $this->assertNotNull($audit);
        /*
         * An audit entry is read by more people than the response was, and it
         * has its own retention and its own export. A secret in it is a secret
         * in all three.
         */
        $this->assertStringNotContainsString($secret, json_encode($audit));
    }

    public function test_revocation_takes_effect_on_the_next_request(): void
    {
        $this->makeTicket();
        $token = $this->issue([Capabilities::TICKET_READ]);

        $this->asClient($token)->getJson('/api/v1/tickets')->assertOk();

        $id = (int) DB::table('personal_access_tokens')->value('id');

        $this->actingAs($this->administrator, 'web')
            ->withIdempotencyKey()
            ->deleteJson('/api/v1/api-clients/'.$id)
            ->assertNoContent();

        // Not a cache expiry and not at the next deploy: Sanctum looks the
        // token up on every call, and a deleted row authenticates nobody.
        $this->asClient($token)->getJson('/api/v1/tickets')->assertUnauthorized();
    }

    public function test_issuing_and_revoking_are_audited(): void
    {
        $token = $this->issue([Capabilities::TICKET_READ]);
        $id = (int) DB::table('personal_access_tokens')->value('id');

        $this->actingAs($this->administrator, 'web')
            ->withIdempotencyKey()
            ->deleteJson('/api/v1/api-clients/'.$id);

        $actions = DB::table('audit_entries')->pluck('action')->all();

        $this->assertContains('api_client.issued', $actions);
        $this->assertContains('api_client.revoked', $actions);

        $issued = DB::table('audit_entries')->where('action', 'api_client.issued')->first();
        $this->assertNotNull($issued->actor_id);
    }

    public function test_a_credential_in_the_url_is_refused_rather_than_ignored(): void
    {
        $token = $this->issue([Capabilities::TICKET_READ]);

        $response = $this->asClient($token)->getJson('/api/v1/tickets?token='.$token);

        /*
         * Refused even though the header was also present and would have
         * worked. Accepting it would leave an integration working by accident
         * until somebody removed the header — and the URL is already in an
         * access log by the time anybody reads this.
         */
        $response->assertStatus(400);
        $this->assertSame('security.credential_in_url', $response->json('code'));
    }

    public function test_a_client_is_rate_limited_per_token_with_the_retry_interval(): void
    {
        $this->settings()->set('security.api.requests_per_minute', 3, null);
        $this->makeTicket();

        $token = $this->issue([Capabilities::TICKET_READ]);

        for ($i = 0; $i < 3; $i++) {
            $this->asClient($token)->getJson('/api/v1/tickets')->assertOk();
        }

        $refused = $this->asClient($token)->getJson('/api/v1/tickets');

        $refused->assertStatus(429);

        /*
         * Never a bare 429 with an empty body. A machine that is told to slow
         * down and not told for how long will retry immediately, which is the
         * behaviour the limit exists to stop.
         */
        $this->assertNotEmpty($refused->getContent());
        $this->assertNotNull($refused->headers->get('Retry-After'));
        $this->assertNotNull($refused->headers->get('X-RateLimit-Limit'));
    }

    public function test_two_clients_do_not_share_a_budget(): void
    {
        $this->settings()->set('security.api.requests_per_minute', 2, null);
        $this->makeTicket();

        $first = $this->issue([Capabilities::TICKET_READ]);
        $second = $this->issue([Capabilities::TICKET_READ]);

        $this->asClient($first)->getJson('/api/v1/tickets')->assertOk();
        $this->asClient($first)->getJson('/api/v1/tickets')->assertOk();
        $this->asClient($first)->getJson('/api/v1/tickets')->assertStatus(429);

        /*
         * Keyed on the TOKEN, not the address. Two integrations behind one NAT
         * gateway would otherwise throttle each other for reasons neither of
         * them could see.
         */
        $this->asClient($second)->getJson('/api/v1/tickets')->assertOk();
    }

    public function test_a_write_still_needs_an_idempotency_key(): void
    {
        $token = $this->issue([Capabilities::TICKET_READ, Capabilities::TICKET_CREATE]);

        $response = $this->asClient($token)->postJson('/api/v1/tickets', [
            'subject' => 'From a machine',
            'description' => 'Raised by an integration.',
            'customer_id' => $this->makeCustomer(),
        ]);

        // The same middleware the interface passes. A machine retrying a
        // timed-out request must not raise two tickets.
        $this->assertNotSame(201, $response->status());
    }

    public function test_a_write_carries_the_version_guard_exactly_as_the_interface_does(): void
    {
        $ticket = $this->makeTicket(['status' => 'open']);

        $token = $this->issue([
            Capabilities::TICKET_READ,
            Capabilities::TICKET_UPDATE,
            Capabilities::TICKET_CHANGE_STATUS,
        ]);

        $stale = $this->asClient($token)
            ->withHeader('Idempotency-Key', (string) \Illuminate\Support\Str::ulid())
            ->patchJson('/api/v1/tickets/'.$ticket->getKey(), [
                'version' => $ticket->version + 5,
                'status' => 'pending',
            ]);

        /*
         * The five contended properties are guarded for a machine exactly as
         * they are for a person. An integration that could overwrite a
         * colleague's edit without noticing would be the version guard's whole
         * failure case, arriving through a door nobody watches.
         */
        $stale->assertStatus(409);
        $this->assertSame('open', $ticket->refresh()->status->value);
    }

    public function test_an_error_is_a_problem_document_with_a_machine_code(): void
    {
        $token = $this->issue([Capabilities::TICKET_READ]);

        $response = $this->asClient($token)->getJson('/api/v1/tickets/01JQZ0000000000000000000ZZ');

        $response->assertStatus(404);
        $response->assertHeader('Content-Type', 'application/problem+json');

        // A code and a type URI a machine can branch on — never a sentence
        // meant for a person to read on a screen.
        $this->assertNotEmpty($response->json('code'));
        $this->assertStringStartsWith('https://', (string) $response->json('type'));
    }

    public function test_a_client_with_no_token_reaches_nothing(): void
    {
        $this->makeTicket();

        $this->getJson('/api/v1/tickets')->assertUnauthorized();
    }

    public function test_only_an_administrator_may_issue_a_client(): void
    {
        $supervisor = $this->makeUser(Roles::SUPERVISOR);

        $this->actingAs($supervisor, 'web')
            ->withIdempotencyKey()
            ->postJson('/api/v1/api-clients', [
                'name' => 'Not yours to make',
                'owner_id' => $this->service->getKey(),
                'abilities' => [Capabilities::TICKET_READ],
            ])
            ->assertForbidden();
    }

    public function test_the_version_is_in_the_path_and_not_a_header(): void
    {
        $routes = (string) file_get_contents(base_path('routes/api.php'));

        /*
         * A version in a header is a version nobody can see in a log, a curl
         * command or a browser address bar — and a client that forgets it gets
         * whichever version happens to be the default.
         */
        $this->assertStringNotContainsString('Accept-Version', $routes);
        $this->assertStringNotContainsString('X-API-Version', $routes);

        $this->assertStringStartsWith('api/v1/', \Illuminate\Support\Facades\Route::getRoutes()
            ->getByName('tickets.index')?->uri() ?? '');
    }
}
