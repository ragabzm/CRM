<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * The property rail's write, and the two ways it carries a version.
 *
 * The version may arrive in the body or as `If-Match` — the same number spelled
 * the way the caller finds natural. There is still ONE guard reading ONE value.
 * A second mechanism would be a second thing to get wrong, and the one that is
 * wrong is the one nobody tested.
 */
final class TicketPropertiesPatchTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->ticket = $this->makeTicket();
    }

    private function railPatch(array $body, array $headers = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->makeUser(Roles::ADMINISTRATOR))
            ->withHeader('Idempotency-Key', (string) Str::ulid())
            ->withHeaders($headers)
            ->patchJson("/api/v1/tickets/{$this->ticket->getKey()}", $body);
    }

    public function test_the_read_hands_back_the_version_as_an_etag(): void
    {
        $response = $this->actingAs($this->makeUser(Roles::ADMINISTRATOR))
            ->getJson("/api/v1/tickets/{$this->ticket->getKey()}")
            ->assertOk();

        // So a client can hand it straight back as If-Match without unpacking
        // JSON to find it.
        $this->assertSame('W/"1"', $response->headers->get('ETag'));
        $this->assertSame(1, $response->json('version'));
    }

    public function test_a_change_with_the_version_in_the_body_succeeds(): void
    {
        $this->railPatch(['version' => 1, 'priority' => 'urgent'])
            ->assertOk()
            ->assertJsonPath('priority', 'urgent')
            ->assertJsonPath('version', 2);
    }

    public function test_a_change_with_the_version_in_if_match_succeeds(): void
    {
        $this->railPatch(['priority' => 'urgent'], ['If-Match' => 'W/"1"'])
            ->assertOk()
            ->assertJsonPath('priority', 'urgent');
    }

    public function test_a_bare_number_in_if_match_is_accepted(): void
    {
        // What a client sends when it built the header by hand. Refusing it
        // would be pedantry that costs a working request.
        $this->railPatch(['priority' => 'high'], ['If-Match' => '1'])->assertOk();
    }

    public function test_the_body_wins_when_both_are_present(): void
    {
        // An explicit field beats a header: the field is what the form actually
        // submitted.
        $this->railPatch(['version' => 1, 'priority' => 'high'], ['If-Match' => 'W/"99"'])->assertOk();
    }

    public function test_a_stale_if_match_is_refused(): void
    {
        $this->railPatch(['version' => 1, 'priority' => 'urgent'])->assertOk();

        $response = $this->railPatch(['status' => 'pending'], ['If-Match' => 'W/"1"'])
            ->assertStatus(409);

        $response->assertJsonPath('code', 'tickets.stale_version');
    }

    public function test_the_refusal_says_what_the_version_actually_is(): void
    {
        $this->railPatch(['version' => 1, 'priority' => 'urgent'])->assertOk();

        $response = $this->railPatch(['version' => 1, 'status' => 'pending'])->assertStatus(409);

        // Enough for the screen to offer "Reload" and mean it, rather than
        // telling the agent to guess.
        $this->assertSame(2, $response->json('current_version'));
        $this->assertSame(1, $response->json('submitted_version'));
        $this->assertNotNull($response->json('current'));
    }

    public function test_a_refused_change_changes_nothing(): void
    {
        $this->railPatch(['version' => 1, 'priority' => 'urgent'])->assertOk();
        $this->railPatch(['version' => 1, 'priority' => 'low'])->assertStatus(409);

        $this->assertSame('urgent', $this->ticket->refresh()->priority->value);
    }

    public function test_a_change_with_no_version_at_all_is_refused(): void
    {
        // Omitting it would be silently opting out of the protection the whole
        // mechanism exists for.
        $this->railPatch(['priority' => 'urgent'])->assertStatus(422);
    }

    public function test_every_rail_field_is_editable(): void
    {
        $agent = $this->makeUser(Roles::AGENT);
        $department = $this->makeDepartment('Support');

        $version = 1;

        foreach ([
            ['priority' => 'high'],
            ['status' => 'pending'],
            ['assignee_id' => $agent->getKey()],
            ['department_id' => $department],
        ] as $change) {
            $this->railPatch(['version' => $version, ...$change])
                ->assertOk();

            $version = $this->ticket->refresh()->version;
        }

        $this->assertSame('high', $this->ticket->priority->value);
        $this->assertSame('pending', $this->ticket->status->value);
        $this->assertSame($agent->getKey(), $this->ticket->assignee_id);
    }

    public function test_an_unknown_field_is_ignored_rather_than_written(): void
    {
        // SLA is shown on the rail and is not the rail's to set: it is derived
        // from the policy, not typed by an agent.
        $this->railPatch(['version' => 1, 'priority' => 'high', 'sla_due_at' => '2026-01-01'])
            ->assertOk();

        $this->assertArrayNotHasKey('sla_due_at', $this->ticket->refresh()->getAttributes());
    }
}
