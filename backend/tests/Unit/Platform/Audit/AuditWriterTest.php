<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Audit;

use App\Modules\Platform\Audit\Application\AuditWriter;
use App\Modules\Platform\Audit\Domain\AuditAction;
use App\Modules\Platform\Audit\Domain\AuditActorType;
use App\Modules\Platform\Support\RequestContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AuditWriterTest extends TestCase
{
    use RefreshDatabase;

    private function writer(): AuditWriter
    {
        return $this->app->make(AuditWriter::class);
    }

    /** @return object{actor_id:?string,actor_type:string,actor_label:string,source_ip:?string,request_id:?string,before:?string,after:?string,action:string,target_type:?string,target_id:?string} */
    private function lastEntry(): object
    {
        return DB::table('audit_entries')->orderByDesc('id')->first();
    }

    public function test_it_takes_the_actor_request_id_and_ip_from_the_ambient_context(): void
    {
        $context = $this->app->make(RequestContext::class);
        $context->setRequestId('01ABCDEFGHIJKLMNOPQRSTUVWX');
        $context->setActor('user', '41');
        $context->setClientIp('203.0.113.7');

        $this->writer()->record(action: AuditAction::UserUpdated, targetType: 'user', targetId: '41');

        $entry = $this->lastEntry();

        // Not passed in at the call site: every writer inherits the same facts,
        // so no caller can forget one or invent a different answer.
        $this->assertSame('41', $entry->actor_id);
        $this->assertSame('user', $entry->actor_type);
        $this->assertSame('203.0.113.7', $entry->source_ip);
        $this->assertSame('01ABCDEFGHIJKLMNOPQRSTUVWX', $entry->request_id);
    }

    public function test_an_explicit_actor_overrides_the_context(): void
    {
        $this->app->make(RequestContext::class)->setActor('user', '41');

        $this->writer()->record(
            action: AuditAction::SignInFailed,
            actorType: AuditActorType::Guest,
            actorLabel: 'someone@example.test',
        );

        // A failed sign-in must not be filed under whoever happened to be
        // authenticated on that connection.
        $entry = $this->lastEntry();
        $this->assertSame('guest', $entry->actor_type);
        $this->assertNull($entry->actor_id);
        $this->assertSame('someone@example.test', $entry->actor_label);
    }

    public function test_an_unidentified_caller_during_a_request_is_a_guest(): void
    {
        // A request id means an HTTP request is in flight, so nobody signed in
        // is a GUEST — an anonymous caller, not the application itself.
        $this->app->make(RequestContext::class)->setRequestId('01ABCDEFGHIJKLMNOPQRSTUVWX');

        $this->writer()->record(action: AuditAction::SignInFailed);

        $entry = $this->lastEntry();

        // An unattributable action still has to be recorded — that is exactly
        // the action a brute-force review is looking for.
        $this->assertSame('guest', $entry->actor_type);
        $this->assertNull($entry->actor_id);
        $this->assertNotSame('', $entry->actor_label);
    }

    public function test_a_write_with_no_request_in_flight_is_attributed_to_the_service(): void
    {
        // No request id: this is the scheduler or a queue worker acting on the
        // application's own behalf, which is a different fact from "someone we
        // could not identify" and is recorded as one.
        $this->writer()->record(action: AuditAction::ConfigChanged, targetId: 'nightly');

        $entry = $this->lastEntry();
        $this->assertSame('service', $entry->actor_type);
        $this->assertNull($entry->actor_id);
    }

    public function test_the_label_is_stored_rather_than_joined(): void
    {
        $this->app->make(RequestContext::class)->setActor('user', '41');

        $this->writer()->record(
            action: AuditAction::UserUpdated,
            targetType: 'user',
            targetId: '41',
            actorLabel: 'Hana Yousef',
        );

        // Denormalised on purpose: a join would lose the name the moment the
        // account is renamed or removed, which is when someone is reading this.
        $this->assertSame('Hana Yousef', $this->lastEntry()->actor_label);
    }

    public function test_credentials_are_redacted_before_they_reach_the_column(): void
    {
        $this->writer()->record(
            action: AuditAction::UserCreated,
            targetType: 'user',
            targetId: '1',
            after: ['email' => 'a@b.test', 'password' => 'hunter2'],
        );

        // Asserted against the raw column, not the API response: redacting on
        // read would mean the secret really is in the database.
        $raw = (string) $this->lastEntry()->after;
        $this->assertStringNotContainsString('hunter2', $raw);
        $this->assertStringContainsString('[REDACTED]', $raw);
    }

    public function test_an_oversized_payload_becomes_a_marker_rather_than_broken_json(): void
    {
        $this->writer()->record(
            action: AuditAction::ConfigChanged,
            targetType: 'setting',
            targetId: 'big',
            after: ['blob' => str_repeat('x', 100_000)],
        );

        $decoded = json_decode((string) $this->lastEntry()->after, true, 512, JSON_THROW_ON_ERROR);

        // A truncated PREFIX of JSON is not JSON, and would take the row's
        // readability down with it.
        $this->assertTrue($decoded['_truncated']);
        $this->assertGreaterThan(65536, $decoded['sizeBytes']);
    }

    public function test_a_payload_just_under_the_cap_is_stored_intact(): void
    {
        $this->writer()->record(
            action: AuditAction::ConfigChanged,
            targetType: 'setting',
            targetId: 'small',
            after: ['blob' => str_repeat('x', 1000)],
        );

        $decoded = json_decode((string) $this->lastEntry()->after, true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayNotHasKey('_truncated', $decoded);
        $this->assertSame(1000, strlen($decoded['blob']));
    }

    public function test_a_null_payload_stays_null(): void
    {
        $this->writer()->record(action: AuditAction::SignInSucceeded);

        // "Nothing was supplied" and "an empty object was supplied" are
        // different facts about what happened.
        $this->assertNull($this->lastEntry()->before);
    }

    public function test_the_narrow_seam_writes_a_complete_row(): void
    {
        $this->writer()->write(41, AuditAction::ConfigChanged->value, 'setting', 'tickets.auto_close_hours', ['value' => 168], ['value' => 24]);

        $entry = $this->lastEntry();

        // The interface Story 2.3 wrote against must not produce a second-class
        // row that the console then renders with blanks.
        $this->assertSame('config.changed', $entry->action);
        $this->assertSame('setting', $entry->target_type);
        $this->assertSame('tickets.auto_close_hours', $entry->target_id);
        $this->assertSame('user', $entry->actor_type);
        $this->assertSame('41', $entry->actor_id);
    }

    public function test_an_unrecognised_action_is_recorded_rather_than_dropped(): void
    {
        $this->writer()->write(1, 'something.nobody_added', 'thing', '1', [], []);

        // Losing the evidence of what someone did is a much worse bug than a
        // name missing from the enum, and this way the latter is visible.
        $this->assertSame('something.nobody_added', $this->lastEntry()->action);
    }

    public function test_ids_sort_by_the_order_they_were_written(): void
    {
        $ids = [];

        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->writer()->record(action: AuditAction::ConfigChanged, targetId: (string) $i);
        }

        $sorted = $ids;
        sort($sorted);

        // ULIDs are time-ordered, which is what lets the list paginate stably
        // when several entries share a millisecond.
        $this->assertSame($sorted, $ids);
    }

    public function test_the_actor_vocabulary_matches_the_rest_of_the_platform(): void
    {
        // Two names for one concept is how a log records `anonymous` while the
        // correlated log line for the same request says `guest`.
        $this->assertSame(RequestContext::ACTOR_TYPES, AuditActorType::values());
    }
}
