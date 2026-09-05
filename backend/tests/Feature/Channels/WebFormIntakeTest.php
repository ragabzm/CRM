<?php

declare(strict_types=1);

namespace Tests\Feature\Channels;

use App\Modules\Channels\Adapters\WebFormChannelAdapter;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Tickets\Domain\Ticket;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A stranger asks for help, with no account and nothing but a browser.
 *
 * The behaviour that matters is that they end up in exactly the same place a
 * customer who emailed would: one ticket, one customer record, one inbound
 * message row, built by the same commands. If the form ever grows a shortcut,
 * these are the tests that notice.
 */
final class WebFormIntakeTest extends TestCase
{
    use RefreshDatabase;

    private int $departmentId;

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->departmentId = (int) DB::table('departments')->insertGetId([
            'name' => 'Support',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->categoryId = (int) DB::table('ticket_categories')->insertGetId([
            'name_en' => 'Billing',
            'name_ar' => 'الفوترة',
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('channel_accounts')->insert([
            'id' => (string) Str::ulid(),
            'channel' => WebFormChannelAdapter::CHANNEL,
            'name' => 'Public form',
            'is_active' => true,
            'department_id' => $this->departmentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Both limiters, and both keyed the way the middleware keys them.
         *
         * `RateLimiter::clear('web-form-ip')` clears the NAME, not the key the
         * limiter actually counts against — which is `web-form-ip` plus the
         * request's IP, hashed. Every test in this process shares that key, so
         * a test elsewhere that posts eleven web forms silently spends this
         * one's allowance and the rate-limit test fails depending on which
         * order the suite ran in.
         *
         * Clearing the whole cache store is blunt and correct: these limiters
         * live in the cache, nothing else in a test depends on its contents,
         * and a limiter that leaks between tests is a test that passes alone
         * and fails in the suite.
         */
        Cache::clear();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function submission(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Hana Yousef',
            'contact' => 'hana@example.test',
            'subject' => 'My invoice is wrong',
            'category_id' => $this->categoryId,
            'message' => 'I was charged twice for March.',
            'hp_company' => '',
            // Long enough ago to be a person filling in a form.
            'rendered_at' => now()->subSeconds(30)->toIso8601String(),
        ], $overrides);
    }

    public function test_a_submission_becomes_a_ticket_a_customer_and_an_inbound_message(): void
    {
        $response = $this->postJson('/api/v1/inbound/web-form', $this->submission());

        $response->assertCreated();

        $ticket = Ticket::query()->sole();
        $this->assertSame('My invoice is wrong', $ticket->subject);
        $this->assertSame('web_form', $ticket->channel->value);
        $this->assertSame($ticket->reference, $response->json('reference'));

        // The customer did not exist a moment ago and is a real record now.
        $customer = DB::table('customers')->sole();
        $this->assertSame('Hana Yousef', $customer->full_name);
        $this->assertTrue((bool) $customer->auto_created);
        $this->assertSame('inbound_web_form', $customer->created_via);

        $this->assertSame(
            'hana@example.test',
            DB::table('contact_identifiers')->where('kind', 'email')->value('value_normalised'),
        );

        $inbound = DB::table('inbound_messages')->sole();
        $this->assertSame('web_form', $inbound->channel);
        $this->assertSame('correlated', $inbound->delivery_state);
        $this->assertSame((string) $ticket->getKey(), $inbound->ticket_id);
        $this->assertSame('new_ticket', $inbound->correlation_reason);
    }

    public function test_the_ticket_is_built_by_the_commands_not_inserted(): void
    {
        $this->postJson('/api/v1/inbound/web-form', $this->submission())->assertCreated();

        $ticket = Ticket::query()->sole();

        /*
         * The history is the evidence. An inserted ticket has no created event
         * and no version, and looks perfect in a list — this is the assertion
         * that would catch a form that grew its own write path.
         */
        $events = DB::table('ticket_events')
            ->where('ticket_id', $ticket->getKey())
            ->pluck('event_type')
            ->all();

        $this->assertContains('ticket.created', $events);
        $this->assertContains('ticket.message_received', $events);

        $this->assertSame(
            'system',
            DB::table('ticket_events')->where('event_type', 'ticket.created')->value('actor_type'),
        );
        $this->assertSame(
            'inbound_web_form',
            DB::table('ticket_events')->where('event_type', 'ticket.created')->value('actor_reason'),
        );
    }

    public function test_the_category_the_person_chose_reaches_the_ticket(): void
    {
        $this->postJson('/api/v1/inbound/web-form', $this->submission())->assertCreated();

        /*
         * The form asks somebody to choose a category. It was being collected,
         * validated, put in the headers bag and then thrown away — every
         * web-form ticket arrived uncategorised, and the auto-assignment
         * mapping that keys on category could never fire for one.
         *
         * Found in Story 10.2, when a category mapping did nothing for a form
         * submission and everything for an agent's.
         */
        $this->assertSame($this->categoryId, (int) Ticket::query()->sole()->category_id);
    }

    public function test_the_department_comes_from_the_channel_account(): void
    {
        $this->postJson('/api/v1/inbound/web-form', $this->submission())->assertCreated();

        $this->assertSame($this->departmentId, (int) Ticket::query()->sole()->department_id);
        $this->assertSame('channel', DB::table('inbound_messages')->value('department_rule'));
    }

    public function test_a_phone_number_is_accepted_as_readily_as_an_address(): void
    {
        $this->postJson('/api/v1/inbound/web-form', $this->submission([
            'contact' => '+20 100 555 0101',
        ]))->assertCreated();

        $identifier = DB::table('contact_identifiers')->sole();
        $this->assertSame('phone', $identifier->kind);
        // Compared on the trailing digits, the way every other phone is.
        $this->assertSame('1005550101', $identifier->value_normalised);
    }

    public function test_a_second_submission_from_the_same_person_joins_their_open_ticket(): void
    {
        $this->postJson('/api/v1/inbound/web-form', $this->submission())->assertCreated();
        $this->postJson('/api/v1/inbound/web-form', $this->submission([
            'subject' => 'Any news?',
            'message' => 'Following up on the double charge.',
        ]))->assertCreated();

        /*
         * One ticket, not two. A form carries no thread header and no
         * reference, so without the open-ticket rule a customer following up
         * would start a second conversation about the same thing — and the
         * agent would answer one of them.
         */
        $this->assertSame(1, Ticket::query()->count());
        $this->assertSame(
            'open_ticket',
            // By id, not by received_at: the column has second precision and
            // two submissions in one test tie.
            DB::table('inbound_messages')->orderByDesc('id')->value('correlation_reason'),
        );
    }

    public function test_the_open_ticket_rule_stops_when_the_window_closes(): void
    {
        $this->app->make(SettingsRegistry::class)
            ->set('channels.correlation_window_hours.web_form', 1, null);

        $this->postJson('/api/v1/inbound/web-form', $this->submission())->assertCreated();

        DB::table('tickets')->update(['updated_at' => now()->subHours(5)]);

        $this->postJson('/api/v1/inbound/web-form', $this->submission([
            'subject' => 'A different problem',
        ]))->assertCreated();

        // Five hours later is a new conversation, not a follow-up.
        $this->assertSame(2, Ticket::query()->count());
    }

    public function test_a_filled_honeypot_is_accepted_and_ignored(): void
    {
        $response = $this->postJson('/api/v1/inbound/web-form', $this->submission([
            'hp_company' => 'Acme Ltd',
        ]));

        /*
         * 202 and a normal-looking body. Telling a robot it was detected
         * teaches whoever wrote it to stop filling the field in.
         */
        $response->assertStatus(202);
        $this->assertSame(0, Ticket::query()->count());
        $this->assertSame(0, DB::table('inbound_messages')->count());
    }

    public function test_a_submission_faster_than_a_person_is_refused_with_a_reason(): void
    {
        $response = $this->postJson('/api/v1/inbound/web-form', $this->submission([
            'rendered_at' => now()->toIso8601String(),
        ]));

        $response->assertStatus(422);
        $this->assertSame('channels.web_form_too_fast', $response->json('code'));
        // Never a blank body: the person has to be told what to do next.
        $this->assertNotEmpty($response->json('detail'));
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_a_rate_limited_submission_explains_itself(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/inbound/web-form', $this->submission([
                'subject' => 'Message '.$i,
                'contact' => "person{$i}@example.test",
            ]))->assertCreated();
        }

        $response = $this->postJson('/api/v1/inbound/web-form', $this->submission([
            'contact' => 'eleventh@example.test',
        ]));

        $response->assertStatus(429);
        $this->assertNotEmpty($response->getContent());
    }

    public function test_the_client_cannot_choose_its_own_message_id(): void
    {
        $this->postJson('/api/v1/inbound/web-form', $this->submission([
            'provider_message_id' => 'chosen-by-the-client',
        ]))->assertCreated();

        $this->assertNotSame(
            'chosen-by-the-client',
            DB::table('inbound_messages')->value('provider_message_id'),
        );
    }

    public function test_the_six_fields_are_all_required(): void
    {
        foreach (['name', 'contact', 'subject', 'category_id', 'message'] as $field) {
            $payload = $this->submission();
            unset($payload[$field]);

            $this->postJson('/api/v1/inbound/web-form', $payload)
                ->assertStatus(422);
        }
    }

    public function test_a_contact_that_is_neither_an_address_nor_a_number_is_refused(): void
    {
        $this->postJson('/api/v1/inbound/web-form', $this->submission([
            'contact' => 'not a way to reach anybody',
        ]))->assertStatus(422);
    }

    public function test_arabic_survives_the_whole_pipeline(): void
    {
        $this->postJson('/api/v1/inbound/web-form', $this->submission([
            'name' => 'ليلى حداد',
            'subject' => 'فاتورة مكررة',
            'message' => 'تم خصم المبلغ مرتين في مارس.',
        ]))->assertCreated();

        $this->assertSame('فاتورة مكررة', Ticket::query()->sole()->subject);
        $this->assertSame('ليلى حداد', DB::table('customers')->value('full_name'));
        $this->assertSame(
            'تم خصم المبلغ مرتين في مارس.',
            DB::table('ticket_messages')->where('direction', 'inbound')->value('body'),
        );
    }
}
