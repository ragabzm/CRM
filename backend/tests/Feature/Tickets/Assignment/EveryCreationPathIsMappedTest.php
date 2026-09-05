<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets\Assignment;

use App\Models\User;
use App\Modules\Channels\Adapters\WebFormChannelAdapter;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Assignment\AssignmentMapping;
use App\Modules\Tickets\Domain\Commands\CreateTicket;
use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Architecture\SourceScanner;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Feature\Tickets\InteractsWithTickets;
use Tests\TestCase;

/**
 * Every way a ticket can be born goes through the mapping.
 *
 * This is the acceptance criterion most likely to rot. "On every creation
 * path" is true today because there is exactly ONE — `CreateTicket` — and a
 * second one would be a second place to forget. Two of these tests exercise
 * real paths end to end; the third is the one that notices a third path
 * appearing.
 */
final class EveryCreationPathIsMappedTest extends TestCase
{
    use InteractsWithSpaSession;
    use InteractsWithTickets;
    use RefreshDatabase;

    private User $mappedAgent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTickets(Roles::AGENT);

        $this->mappedAgent = User::factory()->create(['department_id' => $this->departmentId]);
        $this->mappedAgent->syncRoles([Roles::AGENT]);

        AssignmentMapping::create([
            'source_type' => 'category',
            'source_id' => $this->categoryId,
            'target_type' => 'agent',
            'target_id' => (int) $this->mappedAgent->getKey(),
        ]);
    }

    public function test_a_ticket_an_agent_raises_is_mapped(): void
    {
        $response = $this->withIdempotencyKey()->postJson('/api/v1/tickets', [
            'subject' => 'Invoice is wrong',
            'description' => 'Charged twice.',
            'customer_id' => $this->customerId,
            'channel' => 'agent',
            'category_id' => $this->categoryId,
        ]);

        $response->assertCreated();
        $this->assertSame((int) $this->mappedAgent->getKey(), $response->json('assignee_id'));
    }

    public function test_a_ticket_the_public_web_form_raises_is_mapped(): void
    {
        DB::table('channel_accounts')->insert([
            'id' => (string) Str::ulid(),
            'channel' => WebFormChannelAdapter::CHANNEL,
            'name' => 'Public form',
            'is_active' => true,
            'department_id' => $this->departmentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/inbound/web-form', [
            'name' => 'Amira Fahmy',
            'contact' => 'amira.fahmy@example.test',
            'subject' => 'The download link expired',
            'category_id' => $this->categoryId,
            'message' => 'It says the link has expired.',
            'hp_company' => '',
            'rendered_at' => now()->subSeconds(30)->toIso8601String(),
        ])->assertCreated();

        /*
         * A stranger's ticket lands on the same person an agent's would. The
         * whole point of putting the mapping inside `CreateTicket` rather than
         * in a controller is that no channel gets to opt out.
         */
        $ticket = Ticket::query()->where('channel', 'web_form')->sole();

        $this->assertSame((int) $this->mappedAgent->getKey(), $ticket->assignee_id);
    }

    public function test_there_is_still_exactly_one_place_a_ticket_can_be_born(): void
    {
        $writers = [];

        foreach (SourceScanner::moduleNames() as $module) {
            foreach (SourceScanner::phpFiles("app/Modules/{$module}") as $file) {
                $code = SourceScanner::codeOnly($file);

                /*
                 * `new Ticket;` with the statement terminator, not the
                 * substring: `new TicketEscalated(...)` and `new
                 * TicketAttributeChanges(...)` are not tickets, and matching
                 * the prefix made this list nine files long and meaningless.
                 */
                $makesOne = str_contains($code, "DB::table('tickets')->insert")
                    || preg_match('/new\s+Ticket\s*[;(]/', $code) === 1
                    || str_contains($code, 'Ticket::create(');

                if ($makesOne) {
                    $writers[] = basename($file);
                }
            }
        }

        sort($writers);

        /*
         * Pinned. "Assignment applies on every creation path" is true because
         * there is one path — and this is what notices a second appearing.
         * `CreateTicket` inserts; `Ticket.php` is the model itself.
         *
         * A new name here means somebody found a way to make a ticket that
         * skips the mapping, the created event and the version — and the
         * mapping is the least of what they skipped.
         */
        $this->assertSame(['CreateTicket.php'], array_values(array_unique($writers)));
    }
}
