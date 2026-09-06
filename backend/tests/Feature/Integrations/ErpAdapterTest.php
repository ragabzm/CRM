<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Integrations\Contracts\ErpResponse;
use App\Modules\Integrations\Contracts\ErpTransport;
use App\Modules\Integrations\Domain\CustomerImport;
use App\Modules\Integrations\Domain\ErpSettings;
use App\Modules\Integrations\Jobs\ErpExchangeJob;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * One adapter, one log, and a failure that is diagnosable rather than a
 * mystery.
 *
 * The tests worth the most here are about ABSENCE and about SECRETS. Absence,
 * because the whole product has to work with this switched off and no provider
 * reachable — an integration nothing depends on is the only kind worth having.
 * Secrets, because the exchange log is read by more people than the code that
 * wrote it, and a credential in it is a credential in a table with its own
 * retention and its own export.
 */
final class ErpAdapterTest extends TestCase
{
    use InteractsWithSpaSession;
    use MakesTickets;
    use RefreshDatabase;

    private const SECRET = 'Bearer sk-live-000-do-not-log-me';

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->administrator = $this->makeUser(Roles::ADMINISTRATOR);
    }

    private function settings(): SettingsRegistry
    {
        return $this->app->make(SettingsRegistry::class);
    }

    private function configure(array $overrides = []): void
    {
        $this->settings()->set(ErpSettings::ENABLED, true, null);
        $this->settings()->set(ErpSettings::ENDPOINT, 'https://erp.example.test/api', null);
        $this->settings()->set(ErpSettings::CREDENTIAL, self::SECRET, null);

        foreach ($overrides as $key => $value) {
            $this->settings()->set($key, $value, null);
        }

        // The redactor is built from the configured secrets; a value set after
        // it was resolved would not be removable.
        $this->app->forgetInstance(\App\Modules\Integrations\Domain\Redactor::class);
        $this->app->forgetInstance(ErpSettings::class);
    }

    /** A transport that answers whatever it is told to, and records the call. */
    private function transport(ErpResponse $response): object
    {
        $double = new class($response) implements ErpTransport
        {
            /** @var list<array<string, mixed>> */
            public array $calls = [];

            public function __construct(private readonly ErpResponse $response) {}

            public function send(string $method, string $url, array $headers, array $body, int $timeoutSeconds): ErpResponse
            {
                $this->calls[] = compact('method', 'url', 'headers', 'body', 'timeoutSeconds');

                return $this->response;
            }

            public function name(): string
            {
                return 'double';
            }
        };

        $this->app->instance(ErpTransport::class, $double);

        return $double;
    }

    private function ok(array $body = ['ok' => true]): ErpResponse
    {
        return new ErpResponse(reached: true, status: 200, body: $body, durationMs: 42);
    }

    public function test_an_exchange_is_logged_with_what_happened(): void
    {
        $this->configure();
        $this->transport($this->ok());

        $this->app->make(\App\Modules\Integrations\Domain\ErpExchange::class)->call('GET', '/customers');

        $row = DB::table('integration_exchanges')->sole();

        $this->assertSame('outbound', $row->direction);
        $this->assertSame('erp', $row->integration);
        $this->assertStringContainsString('erp.example.test', $row->target);
        $this->assertSame('succeeded', $row->status);
        $this->assertSame(200, (int) $row->response_status);
        $this->assertSame(42, (int) $row->duration_ms);
    }

    public function test_a_configured_secret_never_appears_in_any_log_row(): void
    {
        $this->configure();

        // The worst case: the ERP is down and the client library puts the
        // whole request — headers included — into the message it throws.
        $this->transport(ErpResponse::unreachable(
            'Connection refused. Request was: GET https://erp.example.test/api with '.self::SECRET,
            17,
        ));

        $this->app->make(\App\Modules\Integrations\Domain\ErpExchange::class)->call('GET', '/customers');

        $everything = json_encode(DB::table('integration_exchanges')->get());

        /*
         * The single most important assertion in this story. Redaction happens
         * where the row is WRITTEN — against the configured value and a header
         * deny-list — not by a reviewer remembering to omit it.
         */
        $this->assertStringNotContainsString(self::SECRET, (string) $everything);
        $this->assertStringNotContainsString('sk-live-000', (string) $everything);

        // And the row still says enough to diagnose it.
        $this->assertStringContainsString('Connection refused', (string) $everything);
    }

    public function test_the_authorization_header_is_redacted_even_when_it_is_not_configured(): void
    {
        $this->configure();
        $this->transport($this->ok());

        $this->app->make(\App\Modules\Integrations\Domain\ExchangeLog::class)->queued(
            'erp',
            'https://erp.example.test/api',
            ['Authorization' => 'a value nobody told the redactor about', 'X-Api-Key' => 'nor this'],
            [],
        );

        $request = (string) DB::table('integration_exchanges')->orderByDesc('id')->value('request');

        /*
         * The net underneath the configured values: the day somebody adds a
         * credential this class was not told about is the day exact matching
         * has nothing to match.
         */
        $this->assertStringNotContainsString('nobody told the redactor', $request);
        $this->assertStringNotContainsString('nor this', $request);
    }

    public function test_a_credential_is_never_returned_by_any_endpoint(): void
    {
        $this->configure();

        $response = $this->actingAs($this->administrator, 'web')->getJson('/api/v1/admin/settings');

        $response->assertOk();

        // Write-only, through the console and everywhere else. A value that
        // can be read back leaks through a screen share.
        $this->assertStringNotContainsString(self::SECRET, $response->getContent());
    }

    public function test_a_field_map_target_that_is_not_a_customer_field_is_refused_at_save(): void
    {
        $this->expectException(ProblemException::class);

        /*
         * A MAPPED CONTACT BECOMES A CUSTOMER, and nothing maps above it.
         * There is no organisation, no account and no company — and a field
         * map row targeting one is exactly how that grows back.
         */
        $this->settings()->set(ErpSettings::FIELD_MAP, ['CompanyName' => 'organisation_name'], null);
    }

    public function test_a_valid_field_map_is_accepted(): void
    {
        $this->settings()->set(ErpSettings::FIELD_MAP, [
            'CustName' => 'full_name',
            'CustEmail' => 'email',
        ], null);

        $this->assertSame(
            ['CustName' => 'full_name', 'CustEmail' => 'email'],
            $this->settings()->get(ErpSettings::FIELD_MAP),
        );
    }

    public function test_an_import_creates_a_customer_through_the_directory(): void
    {
        $this->configure([ErpSettings::FIELD_MAP => ['CustName' => 'full_name', 'CustEmail' => 'email']]);

        $result = $this->app->make(CustomerImport::class)->import([
            'CustName' => 'Hana Yousef',
            'CustEmail' => 'hana@example.test',
        ]);

        $this->assertTrue($result['created']);
        $this->assertSame('Hana Yousef', DB::table('customers')->where('id', $result['id'])->value('full_name'));
        // Written through the Customers contract, so the reference format and
        // the normalisation rule have exactly one owner.
        $this->assertNotNull(DB::table('customers')->where('id', $result['id'])->value('reference'));
    }

    public function test_a_duplicate_resolves_to_the_existing_customer(): void
    {
        $this->configure([ErpSettings::FIELD_MAP => ['CustName' => 'full_name', 'CustEmail' => 'email']]);

        $import = $this->app->make(CustomerImport::class);

        $first = $import->import(['CustName' => 'Hana Yousef', 'CustEmail' => 'hana@example.test']);
        $second = $import->import(['CustName' => 'Hana Y', 'CustEmail' => 'hana@example.test']);

        /*
         * Somebody already on file who appears in tonight's export is the same
         * person. Importing them twice is how a desk ends up with two
         * histories for one customer and answers half of one.
         */
        $this->assertSame($first['id'], $second['id']);
        $this->assertFalse($second['created']);
        $this->assertSame(1, DB::table('customers')->count());
    }

    public function test_a_missing_mapped_field_fails_the_exchange_and_writes_nothing(): void
    {
        $this->configure([ErpSettings::FIELD_MAP => ['CustName' => 'full_name', 'CustEmail' => 'email']]);

        try {
            // The ERP renamed a column this morning.
            $this->app->make(CustomerImport::class)->import(['CustName' => 'Hana Yousef']);

            $this->fail('A record with a missing mapped field was imported.');
        } catch (ProblemException $e) {
            $this->assertStringContainsString('CustEmail', $e->getMessage().($e->problem->detail ?? ''));
        }

        // NOT a partial customer. A name with no way to reach them appears in
        // searches, gets picked in a duplicate check, and cannot be contacted.
        $this->assertSame(0, DB::table('customers')->count());
    }

    public function test_a_record_with_nobody_in_it_is_refused(): void
    {
        $this->configure([ErpSettings::FIELD_MAP => ['CustName' => 'full_name']]);

        $this->expectException(ProblemException::class);

        $this->app->make(CustomerImport::class)->import(['CustName' => 'Hana Yousef']);
    }

    public function test_the_test_action_runs_the_real_exchange_path(): void
    {
        $this->configure();
        $double = $this->transport($this->ok());

        $response = $this->actingAs($this->administrator, 'web')
            ->withIdempotencyKey()
            ->postJson('/api/v1/integrations/erp/test');

        $response->assertOk();
        $response->assertJsonPath('data.succeeded', true);
        $response->assertJsonPath('data.status', 200);
        $response->assertJsonPath('data.endpoint', 'https://erp.example.test/api');

        /*
         * The SAME path, with the credential header attached, and a log row to
         * prove it. A ping would pass on a wrong credential and fail at 3am on
         * the first real sync — which is what the administrator pressed this
         * button to avoid.
         */
        $this->assertCount(1, $double->calls);
        $this->assertArrayHasKey('Authorization', $double->calls[0]['headers']);
        $this->assertSame(1, DB::table('integration_exchanges')->count());
    }

    public function test_a_failing_test_says_what_went_wrong(): void
    {
        $this->configure();
        $this->transport(ErpResponse::unreachable('Name or service not known', 5));

        $response = $this->actingAs($this->administrator, 'web')
            ->withIdempotencyKey()
            ->postJson('/api/v1/integrations/erp/test');

        $response->assertOk();
        $response->assertJsonPath('data.succeeded', false);

        // The endpoint reached, the timing and the error. "Failed" on its own
        // is a result an administrator can do nothing with.
        $this->assertNotNull($response->json('data.endpoint'));
        $this->assertNotNull($response->json('data.duration_ms'));
        $this->assertStringContainsString('Name or service', (string) $response->json('data.error'));
    }

    public function test_the_job_retries_with_backoff_and_then_stops(): void
    {
        $job = new ErpExchangeJob('GET', '/customers');

        /*
         * Four attempts over about ten minutes, and then a terminal failure in
         * the log. Unbounded retries are worse than none: they turn one broken
         * configuration into a queue that never drains.
         */
        $this->assertSame(4, $job->tries);
        $this->assertSame([30, 60, 120, 300], $job->backoff);

        // And a ceiling above the transport's own timeout, so a socket that
        // never closes cannot hold a worker for ever.
        $this->assertGreaterThan(0, $job->timeout);
    }

    public function test_the_job_does_nothing_when_the_integration_is_switched_off(): void
    {
        $double = $this->transport($this->ok());

        // Deliberately not configured.
        $this->app->make(ErpExchangeJob::class, ['method' => 'GET', 'path' => '/customers']);

        (new ErpExchangeJob('GET', '/customers'))->handle(
            $this->app->make(\App\Modules\Integrations\Domain\ErpExchange::class),
            $this->app->make(ErpSettings::class),
        );

        // Silently done, not failed: an administrator who turned it off meant
        // for it to stop, and a queue of failures would be a log of it obeying.
        $this->assertSame([], $double->calls);
        $this->assertSame(0, DB::table('integration_exchanges')->count());
    }

    public function test_the_whole_ticketing_loop_works_with_the_integration_off(): void
    {
        $agent = $this->makeUser(Roles::AGENT);
        $ticket = $this->makeTicket(['assignee_id' => $agent->getKey()]);

        // Not configured, no provider reachable, nothing bound but the real
        // transport that would fail if anything called it.
        $this->actingAs($agent, 'web')->withIdempotencyKey()
            ->postJson('/api/v1/tickets/'.$ticket->getKey().'/messages', [
                'direction' => 'internal',
                'body' => 'A note.',
            ])->assertCreated();

        $this->actingAs($agent, 'web')->withIdempotencyKey()
            ->patchJson('/api/v1/tickets/'.$ticket->getKey(), [
                'version' => $ticket->refresh()->version,
                'status' => 'resolved',
            ])->assertOk();

        /*
         * Nothing in the loop notices. This module is at the top of the tier
         * list, nothing depends on it, and an ERP unreachable since Tuesday is
         * invisible from every screen.
         */
        $this->assertSame('resolved', $ticket->refresh()->status->value);
        $this->assertSame(0, DB::table('integration_exchanges')->count());
    }

    public function test_the_log_is_pruned_only_by_the_retention_policy(): void
    {
        $this->configure([ErpSettings::RETENTION_DAYS => 30]);
        $this->transport($this->ok());

        $exchange = $this->app->make(\App\Modules\Integrations\Domain\ErpExchange::class);
        $exchange->call('GET', '/recent');

        DB::table('integration_exchanges')->update(['occurred_at' => now()->subDays(45)]);
        $exchange->call('GET', '/today');

        $this->artisan('integrations:prune-log')->assertSuccessful();

        // The old one is gone, today's is not.
        $this->assertSame(1, DB::table('integration_exchanges')->count());
        $this->assertStringContainsString('/today', (string) DB::table('integration_exchanges')->value('target'));
    }

    public function test_there_is_no_way_to_delete_a_log_row_by_hand(): void
    {
        $routes = (string) file_get_contents(base_path('routes/api.php'));

        $start = strpos($routes, "integrations/log");
        $this->assertNotFalse($start);

        /*
         * Retention is the only path a row leaves by. A log somebody can tidy
         * is a log that gets tidied the morning after the thing worth
         * explaining.
         */
        $this->assertStringNotContainsString("Route::delete('/integrations/log", $routes);
    }

    public function test_only_somebody_who_configures_the_product_may_read_the_log(): void
    {
        $agent = $this->makeUser(Roles::AGENT);

        $this->actingAs($agent, 'web')->getJson('/api/v1/integrations/log')->assertForbidden();
        $this->actingAs($this->administrator, 'web')->getJson('/api/v1/integrations/log')->assertOk();
    }

    public function test_an_endpoint_must_be_https(): void
    {
        $this->expectException(ProblemException::class);

        // A credential travelling to an ERP over plain HTTP is a credential on
        // the wire, and "it is an internal network" is a claim nobody can
        // check from here.
        $this->settings()->set(ErpSettings::ENDPOINT, 'http://erp.example.test/api', null);
    }

    public function test_a_credential_is_encrypted_at_rest(): void
    {
        $this->configure();

        $stored = (string) DB::table('settings')
            ->where('key', ErpSettings::CREDENTIAL)
            ->value('value');

        /*
         * A database dump, a read replica and last night's backup all carry
         * this column. Encryption here is not access control — the capability
         * on the route is that — it is what makes a copy of the database not
         * also a copy of somebody else's ERP credentials.
         */
        $this->assertStringNotContainsString(self::SECRET, $stored);
        $this->assertStringNotContainsString('sk-live-000', $stored);

        // And it still round-trips, or the encryption would just be data loss.
        $this->assertSame(self::SECRET, $this->settings()->get(ErpSettings::CREDENTIAL));
    }

    public function test_retries_exhausted_records_a_terminal_failure_in_the_log(): void
    {
        $this->configure();

        (new ErpExchangeJob('GET', '/customers'))->failed(new \RuntimeException('Connection refused'));

        $row = DB::table('integration_exchanges')->where('status', 'abandoned')->sole();

        /*
         * The difference between "it is still retrying" and "this needs a
         * person". Four identical `failed` rows cannot say which, and an
         * administrator should not have to count attempts against a `tries`
         * constant to find out.
         */
        $this->assertSame(4, (int) $row->attempt);
        $this->assertStringContainsString('Connection refused', (string) $row->error);
    }

    public function test_a_missing_mapped_field_states_its_reason_in_the_log(): void
    {
        $this->configure([ErpSettings::FIELD_MAP => ['CustEmail' => 'email']]);

        try {
            $this->app->make(CustomerImport::class)->import(['SomethingElse' => 'x']);
        } catch (ProblemException) {
            // The exception reaches the caller; the row is for the person who
            // reads the console tomorrow morning.
        }

        $row = DB::table('integration_exchanges')->where('status', 'failed')->sole();

        $this->assertStringContainsString('CustEmail', (string) $row->error);
        $this->assertSame(0, DB::table('customers')->count());
    }

    public function test_there_is_one_exchange_log_and_not_one_per_integration(): void
    {
        /*
         * Story 5.1's mail log is a TENANT of this table now, not a second
         * table with the same five columns. An administrator asking "did
         * anything reach the outside world last night?" asks once.
         */
        $this->assertFalse(\Schema::hasTable('mail_log'));

        $this->configure();
        $this->transport($this->ok());
        $this->app->make(\App\Modules\Integrations\Domain\ErpExchange::class)->call('GET', '/customers');

        $this->app->make(\App\Modules\Email\Domain\MailLog::class)
            ->queued('someone@example.test', 'null');

        $integrations = DB::table('integration_exchanges')
            ->distinct()
            ->orderBy('integration')
            ->pluck('integration')
            ->all();

        $this->assertSame(['email', 'erp'], $integrations);
    }

    public function test_one_integrations_retention_never_deletes_anothers_history(): void
    {
        $this->configure([ErpSettings::RETENTION_DAYS => 30]);
        $this->transport($this->ok());

        $this->app->make(\App\Modules\Integrations\Domain\ErpExchange::class)->call('GET', '/customers');
        $this->app->make(\App\Modules\Email\Domain\MailLog::class)->queued('someone@example.test', 'null');

        // Both old enough to be swept by whichever policy owns them.
        DB::table('integration_exchanges')->update(['occurred_at' => now()->subDays(400)]);

        $this->artisan('integrations:prune-log')->assertSuccessful();

        /*
         * THE failure mode a shared table invites: one tenant's retention
         * quietly deleting another's history. The ERP row goes; the email row
         * is Email's to keep, under Email's own configured period.
         */
        $this->assertSame(0, DB::table('integration_exchanges')->where('integration', 'erp')->count());
        $this->assertSame(1, DB::table('integration_exchanges')->where('integration', 'email')->count());
    }

    public function test_changing_direction_or_trigger_is_configuration_and_is_audited(): void
    {
        $this->actingAs($this->administrator, 'web')
            ->withIdempotencyKey()
            ->patchJson('/api/v1/admin/settings/'.ErpSettings::DIRECTION, ['value' => 'both'])
            ->assertOk();

        $this->assertSame('both', $this->settings()->get(ErpSettings::DIRECTION));

        /*
         * Changeable without a redeployment, and ATTRIBUTABLE. "The sync
         * started exporting last Tuesday" needs to be answerable with a name.
         */
        $this->assertTrue(
            DB::table('audit_entries')->where('action', 'config.changed')->exists(),
            'Changing the sync direction left no audit trail.',
        );
    }

    public function test_the_mail_prune_leaves_every_other_integration_alone(): void
    {
        $this->configure();
        $this->transport($this->ok());

        $this->app->make(\App\Modules\Integrations\Domain\ErpExchange::class)->call('GET', '/customers');
        $this->app->make(\App\Modules\Email\Domain\MailLog::class)->queued('someone@example.test', 'null');

        $this->settings()->set('email.log.retention_days', 1, null);
        DB::table('integration_exchanges')->update(['occurred_at' => now()->subDays(400)]);

        $this->artisan('email:prune-log')->assertSuccessful();

        /*
         * The other half of the shared-table risk, and the one the codebase's
         * no-global-scopes rule makes possible: Email filters its rows
         * EXPLICITLY, so a caller can forget to. This is the test that would
         * notice — a mail retention of one day silently taking the ERP's
         * ninety days of history with it.
         */
        $this->assertSame(0, DB::table('integration_exchanges')->where('integration', 'email')->count());
        $this->assertSame(1, DB::table('integration_exchanges')->where('integration', 'erp')->count());
    }

    public function test_the_test_action_cannot_hold_the_interface_for_the_full_sync_timeout(): void
    {
        $this->configure([ErpSettings::TIMEOUT_SECONDS => 120]);
        $double = $this->transport($this->ok());

        $this->actingAs($this->administrator, 'web')
            ->withIdempotencyKey()
            ->postJson('/api/v1/integrations/erp/test')
            ->assertOk();

        /*
         * Sync exchanges run on the queue and may take the configured two
         * minutes; nothing is waiting on them. A person IS waiting on this
         * one, and a request held for two minutes is a browser that looks
         * broken and a worker nobody else can use.
         */
        $this->assertSame(
            \App\Modules\Integrations\Domain\ErpExchange::INTERACTIVE_TIMEOUT_SECONDS,
            $double->calls[0]['timeoutSeconds'],
        );

        // And the queued path still gets the administrator's own number.
        $this->app->make(\App\Modules\Integrations\Domain\ErpExchange::class)->call('GET', '/customers');

        $this->assertSame(120, $double->calls[1]['timeoutSeconds']);
    }
}
