<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Business hours are understood in exactly one class, and time is stored in UTC.
 *
 * Two rules, both about the same failure: a second opinion.
 *
 * If anything but `WorkingHoursCalculator` works out how much of a target has
 * elapsed, then two answers exist — and the day they disagree, a ticket shows
 * as on track on the list and breached on its own screen, and nobody can say
 * which is right.
 *
 * If anything outside the formatting layer converts to a local timezone, then a
 * comparison somewhere is happening in a zone that shifts twice a year, and
 * every deadline moves with it without a single number changing on screen.
 */
final class SlaArithmeticLivesInOnePlaceTest extends TestCase
{
    /** The one class allowed to know what a working hour is. */
    private const CALCULATOR = 'app/Modules/Sla/Domain/WorkingHoursCalculator.php';

    /**
     * Files that name the schedule settings without reasoning about them.
     *
     * The provider DECLARES the settings — their type, default and validation
     * rule. That is a different act from working out how much of a target has
     * elapsed, and it has to happen somewhere.
     */
    private const DECLARES_ONLY = ['app/Modules/Sla/SlaServiceProvider.php'];

    /**
     * Where converting to a display timezone is the whole job.
     *
     * The preview controller is here because naming the zone it is previewing
     * IS formatting — it hands the frontend a UTC instant and the zone's name,
     * and never compares anything in local time.
     */
    private const FORMATTING = [
        'app/Modules/Sla/Domain/WorkingHoursCalculator.php',
        'app/Modules/Sla/Http/Controllers/SlaPreviewController.php',
    ];

    public function test_only_the_calculator_reasons_about_working_hours(): void
    {
        $offenders = [];

        foreach (SourceScanner::phpFiles('app') as $file) {
            $relative = str_replace(SourceScanner::basePath().'/', '', $file);

            if ($relative === self::CALCULATOR || in_array($relative, self::DECLARES_ONLY, true)) {
                continue;
            }

            $code = SourceScanner::codeOnly($file);

            /*
             * The shapes a second implementation takes: reading the schedule
             * or the holiday list directly, or working out a weekday to decide
             * whether the desk was open.
             */
            foreach (["'sla.working_hours'", "'sla.holidays'"] as $needle) {
                if (str_contains($code, $needle)) {
                    $offenders[] = "{$relative} reads {$needle}";
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", $offenders));
    }

    public function test_the_declaring_file_only_declares(): void
    {
        /*
         * The exemption above is by path, so it has to be checked: a provider
         * that started doing arithmetic would be waved through by name.
         */
        $code = SourceScanner::codeOnly(SourceScanner::basePath().'/'.self::DECLARES_ONLY[0]);

        $this->assertStringNotContainsString('workingMinutesBetween', $code);
        $this->assertStringNotContainsString('addWorkingMinutes', $code);
    }

    public function test_the_calculator_actually_exists(): void
    {
        // A rule about one file passes trivially if that file is gone.
        $this->assertFileExists(SourceScanner::basePath().'/'.self::CALCULATOR);
    }

    public function test_nothing_outside_the_formatting_layer_switches_timezone(): void
    {
        $offenders = [];

        foreach (SourceScanner::phpFiles('app') as $file) {
            $relative = str_replace(SourceScanner::basePath().'/', '', $file);

            if (in_array($relative, self::FORMATTING, true)) {
                continue;
            }

            $code = SourceScanner::codeOnly($file);

            /*
             * `setTimezone(` and `->tz(` both move an instant into a local
             * zone. Doing that before a COMPARISON is how a deadline silently
             * shifts by three hours; doing it for display is fine, and display
             * happens in the frontend.
             */
            if (preg_match('/->(setTimezone|tz)\s*\(/', $code) === 1) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, "Timezone conversion outside the formatting layer:\n".implode("\n", $offenders));
    }

    public function test_the_timezone_scan_would_catch_a_real_conversion(): void
    {
        // Guards the guard: the pattern must fire on the calculator, which
        // legitimately converts and is exempted by path rather than by luck.
        $code = SourceScanner::codeOnly(SourceScanner::basePath().'/'.self::CALCULATOR);

        $this->assertSame(1, preg_match('/->(setTimezone|tz)\s*\(/', $code));
    }

    public function test_the_scan_covered_a_believable_number_of_files(): void
    {
        $this->assertGreaterThan(150, count(SourceScanner::phpFiles('app')));
    }
}
