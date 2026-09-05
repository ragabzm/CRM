<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Personal;

use Illuminate\Support\Facades\DB;

/**
 * The two numbers on Home's other tabs.
 *
 * Both tabs' badges, riding along with the counts strip's own request rather
 * than adding one of their own. Home refreshes every thirty seconds and is the
 * busiest screen in the product; a tab badge is not worth a round trip, and
 * two of them are not worth two.
 *
 * Two statements, not one: tasks and mentions are different tables, and
 * folding them into a single aggregate would need a cross join that costs more
 * than the second indexed count it saved. Open and overdue DO come from one
 * pass, because those two are the same rows counted differently and reading
 * them separately is how they end up disagreeing.
 */
final class PersonalCounts
{
    /**
     * @return array{tasks: int, tasks_overdue: int, mentions: int}
     */
    public function forUser(?int $userId): array
    {
        if ($userId === null) {
            return ['tasks' => 0, 'tasks_overdue' => 0, 'mentions' => 0];
        }

        /*
         * `case when … then 1 end` rather than `filter (where …)`, so the same
         * statement runs on SQLite — which the test suite uses. A counts query
         * that only works on Postgres is a counts query no test covers.
         */
        $tasks = DB::table('tasks')
            ->where('user_id', $userId)
            ->whereNull('completed_at')
            ->selectRaw('count(*) as open_count')
            ->selectRaw('count(case when due_at is not null and due_at < ? then 1 end) as overdue_count', [now()])
            ->first();

        $mentions = DB::table('note_mentions')
            ->where('user_id', $userId)
            ->whereNull('read_at')
            ->count();

        return [
            // The badge counts OPEN tasks. Counting completed ones too would
            // show a number that only ever grows and never means "to do".
            'tasks' => (int) ($tasks?->open_count ?? 0),
            'tasks_overdue' => (int) ($tasks?->overdue_count ?? 0),
            'mentions' => $mentions,
        ];
    }
}
