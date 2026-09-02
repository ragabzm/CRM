<?php

declare(strict_types=1);

namespace Tests\Feature\Platform\Audit;

use App\Models\User;
use App\Modules\Platform\Audit\Domain\AuditAction;
use App\Modules\Security\Domain\Department;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * Every action that must leave a trace, exercised through the real HTTP path.
 *
 * Driven through the endpoints rather than by calling the writer, because the
 * thing that breaks in practice is a controller that forgets to record — which
 * a test of the writer alone would never notice.
 */
final class AuditTriggersTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->administrator = User::factory()->create(['name' => 'Root Admin']);
        $this->administrator->syncRoles([Roles::ADMINISTRATOR]);
    }

    /** @return list<object> */
    private function entries(AuditAction $action): array
    {
        return DB::table('audit_entries')->where('action', $action->value)->get()->all();
    }

    private function onlyEntry(AuditAction $action): object
    {
        $entries = $this->entries($action);

        // Exactly one. Two entries for one action means a duplicated call site,
        // and an incident timeline that double-counts is one nobody trusts.
        $this->assertCount(1, $entries, "Expected exactly one [{$action->value}] entry.");

        return $entries[0];
    }

    public function test_a_successful_sign_in_is_recorded(): void
    {
        $user = User::factory()->create(['email' => 'agent@ragab.test', 'password' => bcrypt('Correct-Horse-9')]);
        $user->syncRoles([Roles::AGENT]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'agent@ragab.test',
            'password' => 'Correct-Horse-9',
        ])->assertOk();

        $entry = $this->onlyEntry(AuditAction::SignInSucceeded);
        $this->assertSame('user', $entry->actor_type);
        $this->assertStringContainsString('agent@ragab.test', (string) $entry->after);
    }

    public function test_a_failed_sign_in_is_recorded_without_naming_an_actor(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'agent@ragab.test',
            'password' => 'wrong-password',
        ])->assertStatus(401);

        $entry = $this->onlyEntry(AuditAction::SignInFailed);

        // Whoever typed that email has not proved they own it, so the attempt
        // is not filed under that person's name.
        $this->assertSame('guest', $entry->actor_type);
        $this->assertNull($entry->actor_id);
        $this->assertSame('agent@ragab.test', $entry->actor_label);
    }

    public function test_a_failed_sign_in_never_records_the_attempted_password(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'agent@ragab.test',
            'password' => 'Sup3r-Secret-Guess',
        ])->assertStatus(401);

        // The most valuable thing an attacker could find in an audit log is a
        // near-miss password from a real person's typo.
        $row = json_encode($this->onlyEntry(AuditAction::SignInFailed));
        $this->assertStringNotContainsString('Sup3r-Secret-Guess', (string) $row);
    }

    public function test_creating_a_user_records_what_was_created(): void
    {
        $this->actingAs($this->administrator);

        $this->withIdempotencyKey()->postJson('/api/v1/users', [
            'name' => 'New Person',
            'email' => 'new@ragab.test',
            'role' => Roles::AGENT,
        ])->assertStatus(201);

        $entry = $this->onlyEntry(AuditAction::UserCreated);
        $after = json_decode((string) $entry->after, true);

        $this->assertSame('new@ragab.test', $after['email']);
        $this->assertSame(Roles::AGENT, $after['role']);
        // Neither the plaintext nor the hash reaches the log. The audited view
        // is an allowlist of named fields, so the hash is never even selected.
        $this->assertArrayNotHasKey('password', $after);
    }

    public function test_editing_a_user_records_both_sides_of_the_change(): void
    {
        $this->actingAs($this->administrator);
        $target = User::factory()->create(['name' => 'Before Name']);
        $target->syncRoles([Roles::AGENT]);

        $this->withIdempotencyKey()
            ->patchJson("/api/v1/users/{$target->getKey()}", ['name' => 'After Name'])
            ->assertOk();

        $entry = $this->onlyEntry(AuditAction::UserUpdated);

        // Both sides: an entry recording only the new value cannot answer the
        // question an incident review actually asks.
        $this->assertSame('Before Name', json_decode((string) $entry->before, true)['name']);
        $this->assertSame('After Name', json_decode((string) $entry->after, true)['name']);
    }

    public function test_a_role_change_is_visible_in_the_entry(): void
    {
        $this->actingAs($this->administrator);
        $target = User::factory()->create();
        $target->syncRoles([Roles::AGENT]);

        $this->withIdempotencyKey()
            ->patchJson("/api/v1/users/{$target->getKey()}", ['role' => Roles::SUPERVISOR])
            ->assertOk();

        $entry = $this->onlyEntry(AuditAction::UserUpdated);

        // A privilege escalation is the single most important thing this log
        // has to be able to show.
        $this->assertSame(Roles::AGENT, json_decode((string) $entry->before, true)['role']);
        $this->assertSame(Roles::SUPERVISOR, json_decode((string) $entry->after, true)['role']);
    }

    public function test_deactivating_a_user_is_recorded_as_a_deactivation(): void
    {
        $this->actingAs($this->administrator);
        $target = User::factory()->create();
        $target->syncRoles([Roles::AGENT]);

        $this->withIdempotencyKey()
            ->postJson("/api/v1/users/{$target->getKey()}/deactivate")
            ->assertOk();

        $entry = $this->onlyEntry(AuditAction::UserDeactivated);
        $this->assertTrue((bool) json_decode((string) $entry->before, true)['is_active']);
        $this->assertFalse((bool) json_decode((string) $entry->after, true)['is_active']);
    }

    public function test_deactivating_through_the_edit_form_is_still_a_deactivation(): void
    {
        $this->actingAs($this->administrator);
        $target = User::factory()->create();
        $target->syncRoles([Roles::AGENT]);

        $this->withIdempotencyKey()
            ->patchJson("/api/v1/users/{$target->getKey()}", ['is_active' => false])
            ->assertOk();

        // Recorded as a generic update, this would be invisible to anyone
        // filtering for the one user change that most needs to be findable.
        $this->onlyEntry(AuditAction::UserDeactivated);
        $this->assertCount(0, $this->entries(AuditAction::UserUpdated));
    }

    public function test_department_changes_are_recorded(): void
    {
        $this->actingAs($this->administrator);

        $id = $this->withIdempotencyKey()
            ->postJson('/api/v1/departments', ['name' => 'Billing'])
            ->assertStatus(201)->json('id');

        $this->withIdempotencyKey()
            ->patchJson("/api/v1/departments/{$id}", ['name' => 'Invoicing'])
            ->assertOk();

        $this->onlyEntry(AuditAction::DepartmentCreated);
        $updated = $this->onlyEntry(AuditAction::DepartmentUpdated);

        $this->assertSame('Billing', json_decode((string) $updated->before, true)['name']);
        $this->assertSame('Invoicing', json_decode((string) $updated->after, true)['name']);
    }

    public function test_a_refused_change_records_nothing(): void
    {
        $this->actingAs($this->administrator);
        $department = Department::create(['name' => 'Support', 'is_active' => true]);

        $this->swap(\App\Modules\Security\Contracts\DepartmentUsageProbe::class, new class implements \App\Modules\Security\Contracts\DepartmentUsageProbe
        {
            public function activeTicketCount(int $departmentId): int
            {
                return 3;
            }
        });

        $this->withIdempotencyKey()
            ->postJson("/api/v1/departments/{$department->getKey()}/deactivate")
            ->assertStatus(409);

        // The write is inside the transaction the refusal aborts, so no entry
        // claims a change that never happened.
        $this->assertCount(0, $this->entries(AuditAction::DepartmentDeleted));
    }

    public function test_a_configuration_change_records_one_entry_per_key(): void
    {
        $this->actingAs($this->administrator);

        $this->withIdempotencyKey()
            ->patchJson('/api/v1/admin/settings/tickets.auto_close_hours', ['value' => 24])
            ->assertOk();

        $entry = $this->onlyEntry(AuditAction::ConfigChanged);

        $this->assertSame('setting', $entry->target_type);
        $this->assertSame('tickets.auto_close_hours', $entry->target_id);
        $this->assertSame(168, json_decode((string) $entry->before, true)['value']);
        $this->assertSame(24, json_decode((string) $entry->after, true)['value']);
    }

    public function test_a_secret_setting_change_records_the_change_but_not_the_secret(): void
    {
        $this->actingAs($this->administrator);

        $this->withIdempotencyKey()
            ->patchJson('/api/v1/admin/settings/email.mailbox.password', ['value' => 'hunter2-and-then-some'])
            ->assertOk();

        $entry = $this->onlyEntry(AuditAction::ConfigChanged);

        // That the password changed, and when, and by whom — without the
        // password. Both facts matter and only one of them is dangerous.
        $this->assertStringNotContainsString('hunter2-and-then-some', (string) $entry->after);
        $this->assertSame('email.mailbox.password', $entry->target_id);
    }

    public function test_a_refused_configuration_change_records_nothing(): void
    {
        $this->actingAs($this->administrator);

        $this->withIdempotencyKey()
            ->patchJson('/api/v1/admin/settings/tickets.auto_close_hours', ['value' => 0])
            ->assertStatus(422);

        $this->assertCount(0, $this->entries(AuditAction::ConfigChanged));
    }

    public function test_every_entry_carries_the_request_it_came_from(): void
    {
        $this->actingAs($this->administrator);

        $this->withIdempotencyKey()
            ->withHeader('X-Request-Id', 'correlate-me-12345')
            ->postJson('/api/v1/departments', ['name' => 'Billing'])
            ->assertStatus(201);

        // Ties the entry to that request's log lines and to any problem
        // document it produced, so three sources can be read as one story.
        $this->assertSame('correlate-me-12345', $this->onlyEntry(AuditAction::DepartmentCreated)->request_id);
    }

    public function test_every_entry_names_the_person_readably(): void
    {
        $this->actingAs($this->administrator);

        $this->withIdempotencyKey()
            ->postJson('/api/v1/departments', ['name' => 'Billing'])
            ->assertStatus(201);

        // "Root Admin", not "user 1". A log that renders ids is unreadable a
        // year later, which is exactly when it gets read.
        $this->assertSame('Root Admin', $this->onlyEntry(AuditAction::DepartmentCreated)->actor_label);
    }
}
