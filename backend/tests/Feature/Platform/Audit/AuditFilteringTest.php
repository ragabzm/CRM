<?php

declare(strict_types=1);

namespace Tests\Feature\Platform\Audit;

use App\Models\User;
use App\Modules\Platform\Audit\Application\AuditWriter;
use App\Modules\Platform\Audit\Domain\AuditAction;
use App\Modules\Platform\Audit\Domain\AuditActorType;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

final class AuditFilteringTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
        $this->seed(RolesAndPermissionsSeeder::class);

        $administrator = User::factory()->create(['name' => 'Root Admin']);
        $administrator->syncRoles([Roles::ADMINISTRATOR]);
        $this->actingAs($administrator->refresh());
    }

    private function entry(AuditAction $action, string $actorLabel, string $occurredAt, ?string $actorId = null): void
    {
        Carbon::setTestNow($occurredAt);

        $this->app->make(AuditWriter::class)->record(
            action: $action,
            targetType: 'thing',
            targetId: '1',
            actorType: AuditActorType::User,
            actorId: $actorId ?? '7',
            actorLabel: $actorLabel,
        );

        Carbon::setTestNow();
    }

    private function seedEntries(): void
    {
        $this->entry(AuditAction::UserCreated, 'Hana Yousef', '2026-09-01 09:00:00', '7');
        $this->entry(AuditAction::UserUpdated, 'Hana Yousef', '2026-09-02 09:00:00', '7');
        $this->entry(AuditAction::ConfigChanged, 'Omar Saleh', '2026-09-03 09:00:00', '9');
    }

    /** @return list<string> */
    private function actionsFrom(array $body): array
    {
        return array_column($body['data'], 'action');
    }

    public function test_it_filters_by_action(): void
    {
        $this->seedEntries();

        $body = $this->getJson('/api/v1/audit-entries?action=user.created')->assertOk()->json();

        $this->assertSame(['user.created'], $this->actionsFrom($body));
        $this->assertSame(1, $body['meta']['total']);
    }

    public function test_it_filters_by_actor_id(): void
    {
        $this->seedEntries();

        $body = $this->getJson('/api/v1/audit-entries?actor_id=9')->assertOk()->json();

        $this->assertSame(['config.changed'], $this->actionsFrom($body));
    }

    public function test_it_searches_the_actor_label_case_insensitively(): void
    {
        $this->seedEntries();

        // The name someone remembers, in whatever case they type it — and it
        // keeps working after the account is renamed, because the label is
        // stored rather than joined.
        $body = $this->getJson('/api/v1/audit-entries?actor_search=hana')->assertOk()->json();

        $this->assertCount(2, $body['data']);
    }

    public function test_a_date_range_is_inclusive_at_both_ends(): void
    {
        $this->seedEntries();

        $body = $this->getJson('/api/v1/audit-entries?from=2026-09-01&to=2026-09-02')->assertOk()->json();

        // Both boundary days present: a reader who typed the 1st expects the
        // 1st, not "after midnight on the 1st".
        $this->assertSame(['user.updated', 'user.created'], $this->actionsFrom($body));
    }

    public function test_a_single_day_range_returns_that_day(): void
    {
        $this->seedEntries();

        $body = $this->getJson('/api/v1/audit-entries?from=2026-09-02&to=2026-09-02')->assertOk()->json();

        $this->assertSame(['user.updated'], $this->actionsFrom($body));
    }

    public function test_filters_combine(): void
    {
        $this->seedEntries();

        $body = $this->getJson('/api/v1/audit-entries?actor_search=hana&action=user.updated')
            ->assertOk()->json();

        $this->assertSame(['user.updated'], $this->actionsFrom($body));
    }

    public function test_an_unknown_action_is_refused_rather_than_returning_everything(): void
    {
        $this->seedEntries();

        // Silently ignoring it would show the full log to someone who believes
        // they are looking at one filtered slice of it.
        $this->getJson('/api/v1/audit-entries?action=not.a.real.action')
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_a_backwards_range_is_refused(): void
    {
        $this->getJson('/api/v1/audit-entries?from=2026-09-03&to=2026-09-01')->assertStatus(422);
    }

    public function test_a_malformed_date_is_refused_with_the_expected_format(): void
    {
        // The per-field reason lives in the `errors` member, which is where
        // RFC 9457 puts field-level detail and where the form reads it from.
        $errors = $this->getJson('/api/v1/audit-entries?from=01-09-2026')
            ->assertStatus(422)
            ->json('errors.from');

        $detail = implode(' ', (array) $errors);

        // Naming the timezone matters: "entries on the 1st" means different
        // rows depending on the answer, and the reader cannot see ours.
        $this->assertStringContainsString('UTC', $detail);
    }

    public function test_an_unsupported_facet_is_ignored_rather_than_honoured(): void
    {
        $this->seedEntries();

        // Three facets is the whole surface. A stray parameter must not
        // silently become a filter, and must not error either — a bookmarked
        // URL from a future version should still return rows.
        $body = $this->getJson('/api/v1/audit-entries?target_type=nothing&source_ip=1.1.1.1')
            ->assertOk()->json();

        $this->assertSame(3, $body['meta']['total']);
    }

    public function test_entries_come_back_newest_first(): void
    {
        $this->seedEntries();

        $body = $this->getJson('/api/v1/audit-entries')->assertOk()->json();

        $this->assertSame(['config.changed', 'user.updated', 'user.created'], $this->actionsFrom($body));
    }

    public function test_paging_never_repeats_or_drops_an_entry(): void
    {
        // Same millisecond for all of them: without a tiebreak the database is
        // free to order these differently per query, which duplicates some rows
        // across pages and loses others entirely.
        Carbon::setTestNow('2026-09-04 12:00:00');
        for ($i = 0; $i < 10; $i++) {
            $this->app->make(AuditWriter::class)->record(
                action: AuditAction::ConfigChanged, targetType: 'setting', targetId: (string) $i,
            );
        }
        Carbon::setTestNow();

        $first = $this->getJson('/api/v1/audit-entries?per_page=4&page=1')->json('data');
        $second = $this->getJson('/api/v1/audit-entries?per_page=4&page=2')->json('data');
        $third = $this->getJson('/api/v1/audit-entries?per_page=4&page=3')->json('data');

        $ids = array_merge(
            array_column($first, 'id'),
            array_column($second, 'id'),
            array_column($third, 'id'),
        );

        $this->assertCount(10, $ids);
        $this->assertCount(10, array_unique($ids));
    }

    public function test_the_page_size_is_capped(): void
    {
        $this->getJson('/api/v1/audit-entries?per_page=5000')->assertStatus(422);
    }

    public function test_the_list_omits_the_payloads_but_the_detail_carries_them(): void
    {
        $id = $this->app->make(AuditWriter::class)->record(
            action: AuditAction::ConfigChanged,
            targetType: 'setting',
            targetId: 'x',
            before: ['value' => 1],
            after: ['value' => 2],
        );

        // A list of fifty entries each carrying two JSON documents is a slow
        // page whose payload nobody reads.
        $row = $this->getJson('/api/v1/audit-entries')->json('data.0');
        $this->assertArrayNotHasKey('before', $row);

        $this->getJson("/api/v1/audit-entries/{$id}")
            ->assertOk()
            ->assertJsonPath('before.value', 1)
            ->assertJsonPath('after.value', 2);
    }

    public function test_the_response_publishes_the_action_vocabulary(): void
    {
        $actions = $this->getJson('/api/v1/audit-entries')->assertOk()->json('actions');

        // So the console builds its dropdown from what the server records,
        // rather than from a second list that drifts.
        $this->assertSame(AuditAction::values(), $actions);
    }

    public function test_a_missing_entry_is_a_problem_document(): void
    {
        $this->getJson('/api/v1/audit-entries/01JZZZZZZZZZZZZZZZZZZZZZZZ')
            ->assertStatus(404)
            ->assertJsonPath('code', 'platform.audit_entry_not_found');

        $this->assertSame(0, DB::table('audit_entries')->count());
    }
}
