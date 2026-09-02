<?php

declare(strict_types=1);

namespace Tests\Feature\Platform\Audit;

use App\Models\User;
use App\Modules\Platform\Audit\Application\AuditWriter;
use App\Modules\Platform\Audit\Domain\AuditAction;
use App\Modules\Platform\Audit\Domain\AuditEntry;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * The one property this table exists for.
 *
 * Checked at all three layers, because each covers a hole the others leave: the
 * HTTP surface stops a request, the model stops application code, and the
 * schema leaves nowhere for a mutation to record itself.
 */
final class AuditImmutabilityTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    private string $entryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
        $this->seed(RolesAndPermissionsSeeder::class);

        $administrator = User::factory()->create();
        $administrator->syncRoles([Roles::ADMINISTRATOR]);
        $this->actingAs($administrator->refresh());

        $this->entryId = $this->app->make(AuditWriter::class)->record(
            action: AuditAction::ConfigChanged,
            targetType: 'setting',
            targetId: 'tickets.auto_close_hours',
            before: ['value' => 168],
            after: ['value' => 24],
        );
    }

    public function test_the_http_surface_offers_no_way_to_change_an_entry(): void
    {
        foreach (['putJson', 'patchJson'] as $method) {
            $this->withIdempotencyKey()
                ->{$method}("/api/v1/audit-entries/{$this->entryId}", ['action' => 'tampered'])
                ->assertStatus(405);
        }

        $this->withIdempotencyKey()
            ->deleteJson("/api/v1/audit-entries/{$this->entryId}")
            ->assertStatus(405);
    }

    public function test_a_refused_write_is_still_an_rfc_9457_document(): void
    {
        // A 405 that returns Laravel's HTML error page would break every client
        // that parses problem+json, including our own.
        $this->withIdempotencyKey()
            ->deleteJson("/api/v1/audit-entries/{$this->entryId}")
            ->assertStatus(405)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonStructure(['type', 'title', 'status', 'code']);
    }

    public function test_the_entry_is_untouched_after_every_attempt(): void
    {
        $this->withIdempotencyKey()->putJson("/api/v1/audit-entries/{$this->entryId}", ['action' => 'x']);
        $this->withIdempotencyKey()->deleteJson("/api/v1/audit-entries/{$this->entryId}");

        $this->assertDatabaseHas('audit_entries', [
            'id' => $this->entryId,
            'action' => AuditAction::ConfigChanged->value,
        ]);
    }

    public function test_saving_a_stored_entry_throws(): void
    {
        $entry = AuditEntry::query()->findOrFail($this->entryId);
        $entry->action = 'tampered';

        // Loudly at the call site, rather than quietly succeeding.
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('append-only');

        $entry->save();
    }

    public function test_updating_a_stored_entry_throws(): void
    {
        $entry = AuditEntry::query()->findOrFail($this->entryId);

        $this->expectException(LogicException::class);

        // `update()` does not route through `save()` on every path, so it is
        // blocked separately rather than assumed covered.
        $entry->update(['action' => 'tampered']);
    }

    public function test_deleting_an_entry_throws(): void
    {
        $entry = AuditEntry::query()->findOrFail($this->entryId);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('append-only');

        $entry->delete();
    }

    public function test_the_table_has_nowhere_to_record_a_mutation(): void
    {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('audit_entries');

        // No updated_at and no soft-delete column: the shape of the table is
        // itself part of the guarantee, not just the code around it.
        $this->assertNotContains('updated_at', $columns);
        $this->assertNotContains('deleted_at', $columns);
        $this->assertContains('occurred_at', $columns);
    }

    public function test_no_write_verb_is_registered_for_the_audit_surface(): void
    {
        $verbs = [];

        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/v1/audit-entries')) {
                $verbs = array_merge($verbs, $route->methods());
            }
        }

        // GET and HEAD only. A write route existing at all — even one that
        // refuses — is a route someone can be talked into relaxing.
        $this->assertSame(['GET', 'HEAD'], array_values(array_unique($verbs)));
    }

    public function test_the_writer_never_reuses_an_id_under_load(): void
    {
        $writer = $this->app->make(AuditWriter::class);

        for ($i = 0; $i < 100; $i++) {
            $writer->record(action: AuditAction::ConfigChanged, targetType: 'setting', targetId: (string) $i);
        }

        // App-side ULIDs, so there is no sequence for concurrent writers to
        // contend over — and no chance of two entries colliding onto one row.
        $this->assertSame(101, DB::table('audit_entries')->count());
        $this->assertSame(101, DB::table('audit_entries')->distinct()->count('id'));
    }
}
