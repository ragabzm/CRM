<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTickets;
use Tests\Support\RunsAgainstRealPostgres;
use Tests\TestCase;

/**
 * The ticket search path production actually runs.
 *
 * The rest of the suite runs on SQLite and exercises the portable LIKE
 * fallback. That is a genuinely different implementation — tsvector tokenises
 * and ranks, trigram tolerates a half-remembered reference, and containment
 * does neither — so testing only the fallback would mean the production query
 * has never been executed by anything but a person clicking around.
 *
 * Skips when no Postgres is reachable, so the suite still runs anywhere. A skip
 * rather than a silent pass: the reason is printed, so nobody reads green
 * output and concludes the indexed paths were covered.
 */
final class TicketSearchPostgresTest extends TestCase
{
    use RunsAgainstRealPostgres;
    use MakesTickets;


    protected function setUp(): void
    {
        parent::setUp();

        $this->useRealPostgres('the Postgres search path');

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function tearDown(): void
    {
        // Leaves no tables behind in a database someone may also be using by
        // hand.
        $this->releaseRealPostgres();

        parent::tearDown();
    }


    /** @return list<string> */
    private function search(string $term): array
    {
        $body = $this->actingAs($this->makeUser(Roles::SUPERVISOR))
            ->getJson('/api/v1/tickets?q='.rawurlencode($term))
            ->assertOk()
            ->json();

        return array_column($body['data'], 'reference');
    }

    public function test_the_generated_search_column_exists(): void
    {
        // Generated rather than trigger-maintained, so it cannot drift from the
        // row it describes.
        $this->assertTrue(\Schema::hasColumn('tickets', 'search_vector'));
    }

    public function test_the_indexes_that_make_the_list_fast_exist(): void
    {
        $indexes = DB::table('pg_indexes')
            ->where('tablename', 'tickets')
            ->pluck('indexname')
            ->all();

        // Without these the list and the search regress from indexed lookups to
        // sequential scans, which at fifty thousand rows is the difference
        // between a second and a minute.
        foreach ([
            'tickets_search_vector_idx',
            'tickets_reference_trgm_idx',
            'tickets_status_assignee_idx',
        ] as $index) {
            $this->assertContains($index, $indexes);
        }
    }

    public function test_it_finds_a_ticket_by_a_word_in_its_subject(): void
    {
        $matched = $this->makeTicket(['subject' => 'Duplicate charge on the invoice']);
        $this->makeTicket(['subject' => 'Password reset request']);

        $this->assertSame([$matched->reference], $this->search('duplicate'));
    }

    public function test_it_finds_a_ticket_by_a_word_in_its_description(): void
    {
        $matched = $this->makeTicket(['description' => 'The seat was billed twice in August']);
        $this->makeTicket(['description' => 'Nothing relevant here']);

        $this->assertSame([$matched->reference], $this->search('billed'));
    }

    public function test_it_finds_arabic_content(): void
    {
        $arabic = $this->makeTicket(['subject' => 'الفاتورة فيها رسوم مكرّرة']);
        $this->makeTicket(['subject' => 'Password reset']);

        /*
         * The reason the vector uses the 'simple' configuration rather than
         * 'english': the English stemmer would mangle Arabic while helping
         * nothing, and half the tickets in this system are written in Arabic.
         */
        $this->assertSame([$arabic->reference], $this->search('مكرّرة'));
    }

    public function test_two_words_narrow_rather_than_widen(): void
    {
        $both = $this->makeTicket(['subject' => 'Duplicate invoice charge']);
        $this->makeTicket(['subject' => 'Duplicate account']);

        // websearch_to_tsquery treats a space as AND, which is what a person
        // typing two words means.
        $this->assertSame([$both->reference], $this->search('duplicate invoice'));
    }

    public function test_it_finds_a_ticket_by_a_fragment_of_its_reference(): void
    {
        $ticket = $this->makeTicket();
        $this->makeTicket();

        // The useful part of a reference an agent remembers is usually the
        // tail, which no btree can answer.
        $this->assertContains($ticket->reference, $this->search(substr($ticket->reference, -4)));
    }

    public function test_a_reference_search_ranks_the_closest_match_first(): void
    {
        $exact = $this->makeTicket();

        for ($i = 0; $i < 3; $i++) {
            $this->makeTicket();
        }

        // An agent pasting a whole reference expects that ticket at the top,
        // not somewhere in a list of near misses.
        $this->assertSame($exact->reference, $this->search($exact->reference)[0] ?? null);
    }

    public function test_search_results_are_still_scoped_to_the_caller(): void
    {
        $owner = $this->makeUser(Roles::AGENT);
        $this->makeTicket(['subject' => 'Confidential duplicate charge', 'assignee_id' => $owner->getKey()]);

        $outsider = $this->makeUser(Roles::AGENT);

        $body = $this->actingAs($outsider)
            ->getJson('/api/v1/tickets?q=duplicate')
            ->assertOk()
            ->json();

        // Search is not a way around visibility. The scope is applied before
        // any caller-supplied filter, including the search term.
        $this->assertSame([], $body['data']);
    }
}
