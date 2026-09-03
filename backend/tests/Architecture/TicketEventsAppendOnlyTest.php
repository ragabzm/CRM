<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Tickets\Domain\History\TicketEventKind;
use PHPUnit\Framework\TestCase;

/**
 * The history stays append-only as the code around it changes.
 *
 * The runtime guards — the model's throwing overrides, the DB grant — protect
 * today's code. These protect tomorrow's: a route added in a hurry, an observer
 * introduced to "just sync one field", a column that quietly records when a row
 * was last edited. Each of those is a small, reasonable-looking change that
 * ends the guarantee, and none of them would fail any other test.
 */
final class TicketEventsAppendOnlyTest extends TestCase
{
    public function test_no_write_route_targets_the_history(): void
    {
        $routes = SourceScanner::codeOnly(SourceScanner::basePath('routes/api.php'));

        // Only GET on this URI. A write route is the easiest of the three
        // layers to open by accident, and the hardest to notice in review.
        $this->assertDoesNotMatchRegularExpression(
            '/Route::(post|put|patch|delete)\s*\(\s*[\'"][^\'"]*events/i',
            $routes,
            'Only GET may be registered on a ticket history URI.',
        );
    }

    public function test_the_history_route_is_registered_as_a_get(): void
    {
        // The negative test above passes trivially if the route disappears.
        $this->assertMatchesRegularExpression(
            '/Route::get\s*\(\s*[\'"]\/\{ticket\}\/events/',
            SourceScanner::codeOnly(SourceScanner::basePath('routes/api.php')),
        );
    }

    public function test_nothing_observes_the_event_model(): void
    {
        $offenders = [];

        foreach (SourceScanner::phpFiles('app') as $file) {
            $code = SourceScanner::codeOnly($file);

            if (preg_match('/TicketEvent::observe\s*\(/', $code) === 1) {
                $offenders[] = $file;
            }

            if (preg_match('/class\s+TicketEventObserver/', $code) === 1) {
                $offenders[] = $file;
            }
        }

        /*
         * An observer would be a second, invisible writer of history — the one
         * place where "something else also changed the row" must be impossible.
         */
        $this->assertSame([], $offenders, 'The ticket event model must have no observers.');
    }

    public function test_the_schema_records_no_update_time(): void
    {
        $migrations = SourceScanner::phpFiles('app/Modules/Tickets/Database/Migrations');
        $creating = null;

        foreach ($migrations as $file) {
            if (str_contains($file, 'create_ticket_events_table')) {
                $creating = SourceScanner::codeOnly($file);
            }
        }

        $this->assertNotNull($creating, 'The ticket_events migration should exist.');

        // `timestamps()` would add updated_at; a bare `timestamp('created_at')`
        // is what an append-only table wants.
        $this->assertStringNotContainsString('$table->timestamps(', $creating);
        $this->assertStringNotContainsString('updated_at', $creating);
        $this->assertStringNotContainsString('softDeletes', $creating);
    }

    public function test_the_model_closes_every_write_method(): void
    {
        $model = SourceScanner::codeOnly(
            SourceScanner::basePath('app/Modules/Tickets/Domain/TicketEvent.php')
        );

        // One unguarded method is a whole open door.
        foreach (['function update(', 'function updateOrFail(', 'function delete(', 'function forceDelete(', 'function save('] as $method) {
            $this->assertStringContainsString($method, $model, "TicketEvent must override {$method}");
        }
    }

    public function test_only_the_recorder_writes_events(): void
    {
        $writers = [];

        foreach (SourceScanner::phpFiles('app') as $file) {
            if (str_contains($file, 'TicketEventRecorder.php')) {
                continue;
            }

            $code = SourceScanner::codeOnly($file);

            if (preg_match('/new\s+TicketEvent\b|TicketEvent::query\(\)\s*->\s*(insert|create)/', $code) === 1) {
                $writers[] = $file;
            }
        }

        /*
         * One writer, so the event and the change it describes cannot come
         * apart. A second write site is a second place that can forget the
         * transaction the recorder insists on.
         */
        $this->assertSame([], $writers, 'Only TicketEventRecorder may write ticket events.');
    }

    public function test_every_kind_a_write_path_uses_is_enumerated(): void
    {
        $used = [];

        foreach (SourceScanner::phpFiles('app') as $file) {
            preg_match_all(
                '/TicketEventKind::([A-Za-z]+)/',
                SourceScanner::codeOnly($file),
                $matches,
            );

            $used = [...$used, ...$matches[1]];
        }

        $known = array_map(static fn (TicketEventKind $k): string => $k->name, TicketEventKind::cases());

        // The enum's own static methods are not cases.
        $used = array_diff($used, ['from', 'tryFrom', 'cases', 'class']);

        // A typo'd kind is a row nobody can ever find again.
        foreach (array_unique($used) as $case) {
            $this->assertContains($case, $known, "Unknown ticket event kind: {$case}");
        }
    }

    public function test_kinds_with_no_write_path_yet_say_so(): void
    {
        $enum = SourceScanner::basePath('app/Modules/Tickets/Domain/History/TicketEventKind.php');
        $source = file_get_contents($enum);

        foreach (TicketEventKind::cases() as $kind) {
            if ($kind->isEmittedYet()) {
                continue;
            }

            /*
             * A reserved kind and a forgotten one look identical in an enum.
             * The marker is what tells the next reader which this is — and
             * which story is supposed to remove it.
             */
            $this->assertMatchesRegularExpression(
                '/TODO\(Story[^)]*\)[^;]*?case '.$kind->name.'/s',
                (string) $source,
                "{$kind->name} is not emitted anywhere and needs a TODO(Story …) marker.",
            );
        }
    }

    public function test_the_scan_covered_a_believable_number_of_files(): void
    {
        // A broken walk would find no offenders and pass every test above.
        $this->assertGreaterThan(150, count(SourceScanner::phpFiles('app')));
    }
}
