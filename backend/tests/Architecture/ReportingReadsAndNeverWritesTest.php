<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Reporting stays a reader, and stays small.
 *
 * Three refusals, and all three are the kind that get overturned by one
 * reasonable-looking commit.
 *
 * IT OWNS NO TABLE AND WRITES NOTHING. The first time a report is slow, the
 * obvious move is a summary table refreshed nightly — and from then on the
 * figures are true as of the last successful sync, which nobody checks. This
 * module adds no infrastructure at all: no warehouse, no read replica, no ETL,
 * no aggregation table, no migration of its own.
 *
 * IT EVALUATES NO SLA TARGET. Compliance is read from rows written when a
 * breach happened. A target key appearing in this module means a past period
 * has started answering differently depending on today's settings.
 *
 * IT EXPORTS NOTHING. No CSV, no PDF, no print view, no download and no share
 * link — that whole board is out of scope, and the first `Content-Disposition`
 * is where it comes back.
 */
final class ReportingReadsAndNeverWritesTest extends TestCase
{
    /** @return list<string> */
    private function sources(): array
    {
        return SourceScanner::phpFiles('app/Modules/Reporting');
    }

    public function test_it_writes_nothing(): void
    {
        $violations = [];

        foreach ($this->sources() as $file) {
            $code = SourceScanner::codeOnly($file);

            foreach (['->insert(', '->update(', '->delete(', '->upsert(', '->truncate(', '->save(', '->insertGetId('] as $write) {
                if (str_contains($code, $write)) {
                    $violations[] = basename($file).' writes via '.$write;
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Reporting reads facts recorded when they happened.\n".implode("\n", $violations),
        );
    }

    public function test_it_owns_no_table(): void
    {
        $this->assertFalse(
            is_dir(SourceScanner::basePath('app/Modules/Reporting/Database')),
            'Reporting has grown a migration. It owns no table — no aggregation table, '.
            'no warehouse, no ETL. Adding this module adds no infrastructure.',
        );
    }

    public function test_it_evaluates_no_sla_target(): void
    {
        $violations = [];

        foreach ($this->sources() as $file) {
            $code = SourceScanner::codeOnly($file);

            foreach (['target_seconds', 'targetMinutes', 'at_risk_threshold'] as $needle) {
                if (str_contains($code, $needle)) {
                    $violations[] = basename($file).' reaches for a target ('.$needle.')';
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Compliance comes from what was recorded, not from today's targets.\n".implode("\n", $violations),
        );
    }

    public function test_nothing_can_be_exported(): void
    {
        $violations = [];

        foreach ($this->sources() as $file) {
            $code = SourceScanner::codeOnly($file);

            foreach (['Content-Disposition', 'fputcsv', 'StreamedResponse', 'text/csv', 'application/pdf', 'download('] as $needle) {
                if (str_contains($code, $needle)) {
                    $violations[] = basename($file).' exports ('.$needle.')';
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_the_reduced_shapes_have_not_come_back(): void
    {
        $violations = [];

        foreach ($this->sources() as $file) {
            $code = SourceScanner::codeOnly($file);

            /*
             * Named in the story as reduced deliberately. Each is a figure
             * somebody will ask for, and each was cut for a reason recorded in
             * the planning artefacts rather than forgotten.
             */
            foreach ([
                'sealed', 'read_log', 'readLog', 'target_in_force',
                'backlog_age', 'backlogAge', 'reopen_rate', 'reopenRate',
                'self_view', 'selfView', 'saved_report', 'report_builder',
            ] as $needle) {
                if (str_contains($code, $needle)) {
                    $violations[] = basename($file).' contains '.$needle;
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_the_only_filter_is_a_date_range(): void
    {
        $period = new \ReflectionMethod(
            \App\Modules\Reporting\Domain\ReportPeriod::class,
            'between',
        );

        /*
         * Two dates, and no third parameter. A department, a channel or an
         * agent id here is the first half of a report builder — and the
         * product cannot promise a trustworthy answer to an arbitrary
         * question, so it does not offer to take one.
         */
        $this->assertCount(2, $period->getParameters());
    }
}
