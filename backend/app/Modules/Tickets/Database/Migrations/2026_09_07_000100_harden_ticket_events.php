<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Defence in depth for the ticket history.
 *
 * The application already refuses to update or delete an event — the model
 * throws, and no route exposes a write. This adds the two guarantees the
 * application cannot make about itself:
 *
 *   1. A CHECK constraint, so a row that names a system actor AND a person, or
 *      neither, cannot exist however it was written — including by hand in psql
 *      during an incident, which is exactly when someone is tempted to.
 *   2. A grant revoke, so even a compromised application cannot rewrite what
 *      already happened.
 *
 * DO NOT roll this back on a database with real tickets: dropping the
 * constraint is harmless, but re-granting UPDATE and DELETE removes the last
 * thing standing between a disputed change and a rewritten history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            // SQLite (tests) cannot add a constraint to an existing table and
            // has no grants at all. The application-layer guarantees are what
            // the test suite verifies; these two are about the deployment.
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE ticket_events
            ADD CONSTRAINT ticket_events_system_actor_check CHECK (
                (actor_type = 'system') = (actor_id IS NULL)
                AND (actor_type = 'system') = (actor_reason IS NOT NULL)
            )
        SQL);

        /*
         * Wrapped: a deployment whose application user does not own the table
         * cannot revoke on it. The failure is worth knowing about — it means
         * one layer of the defence is absent — but it is not worth refusing to
         * deploy over, because every application-layer guarantee still holds.
         */
        try {
            DB::statement('REVOKE UPDATE, DELETE, TRUNCATE ON ticket_events FROM CURRENT_USER');
        } catch (Throwable $e) {
            Log::warning('Could not revoke UPDATE/DELETE/TRUNCATE on ticket_events.', [
                'reason' => $e->getMessage(),
                'consequence' => 'Ticket history is protected by the application only. '
                    .'An operator should run the REVOKE manually.',
            ]);

            return;
        }

        $this->warnIfTheRevokeCannotBite();
    }

    /**
     * Says so when the revoke above is decorative.
     *
     * A PostgreSQL superuser bypasses every permission check, so the REVOKE
     * succeeds, the grant genuinely disappears from `information_schema`, and
     * `UPDATE ticket_events` still rewrites every row. Checked in this
     * repository's own development database and confirmed: the ACL read
     * `INSERT, REFERENCES, SELECT, TRIGGER` while an UPDATE reported
     * `UPDATE 1`.
     *
     * That combination is worse than not revoking at all, because the evidence
     * an operator would go and look at says the history is protected. Hence a
     * warning naming the actual remedy rather than a silent success.
     */
    private function warnIfTheRevokeCannotBite(): void
    {
        $isSuperuser = (bool) DB::scalar(
            'select rolsuper from pg_roles where rolname = current_user'
        );

        if (! $isSuperuser) {
            return;
        }

        Log::warning('ticket_events: UPDATE/DELETE/TRUNCATE were revoked but will not be enforced.', [
            'reason' => 'The application connects as a PostgreSQL superuser, which bypasses '
                .'every privilege check. The revoke is recorded and has no effect.',
            'remedy' => 'Run the application as a non-superuser role that owns no more than it '
                .'needs. Until then the ticket history is protected by the application layer '
                .'and the CHECK constraint only.',
        ]);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE ticket_events DROP CONSTRAINT IF EXISTS ticket_events_system_actor_check');

        // Deliberately NOT re-granting UPDATE/DELETE/TRUNCATE. A rollback is
        // for undoing a schema change, not for reopening the history to edits.
    }
};
