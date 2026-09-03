<?php

declare(strict_types=1);

namespace Tests\Unit\Sla;

use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Sla\Domain\WorkingHoursCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Four hours to respond" does not mean four hours.
 *
 * It means four hours the desk was open. A ticket raised at 16:30 on Thursday
 * is not late at 20:30 that evening, and a customer would be astonished to hear
 * otherwise. Every number in this file is a moment somebody could argue about,
 * which is exactly why they are pinned.
 *
 * The schedule under test: Sunday–Thursday, 09:00–17:00 Asia/Riyadh (UTC+3),
 * which is a normal week in the deployment this was built for.
 */
final class WorkingHoursCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private function calculator(array $overrides = []): WorkingHoursCalculator
    {
        $settings = $this->app->make(SettingsRegistry::class);

        foreach ($overrides as $key => $value) {
            $settings->set($key, $value, null);
        }

        return new WorkingHoursCalculator($settings);
    }

    /** A UTC instant from a local Riyadh wall-clock time. */
    private function riyadh(string $local): CarbonImmutable
    {
        return CarbonImmutable::parse($local, 'Asia/Riyadh')->setTimezone('UTC');
    }

    public function test_an_hour_inside_the_working_day_is_an_hour(): void
    {
        $minutes = $this->calculator()->workingMinutesBetween(
            $this->riyadh('2026-09-06 10:00'),   // Sunday
            $this->riyadh('2026-09-06 11:00'),
        );

        $this->assertSame(60, $minutes);
    }

    public function test_the_clock_does_not_run_overnight(): void
    {
        $minutes = $this->calculator()->workingMinutesBetween(
            $this->riyadh('2026-09-06 16:30'),   // Sunday, half an hour left
            $this->riyadh('2026-09-07 09:30'),   // Monday, half an hour in
        );

        // Not seventeen hours. The desk was shut for sixteen of them.
        $this->assertSame(60, $minutes);
    }

    public function test_the_clock_does_not_run_before_opening(): void
    {
        $minutes = $this->calculator()->workingMinutesBetween(
            $this->riyadh('2026-09-06 06:00'),
            $this->riyadh('2026-09-06 10:00'),
        );

        // Four hours on the wall, one working hour: the desk opened at nine.
        $this->assertSame(60, $minutes);
    }

    public function test_the_clock_does_not_run_over_a_closed_weekend(): void
    {
        $minutes = $this->calculator()->workingMinutesBetween(
            $this->riyadh('2026-09-10 16:00'),   // Thursday, an hour left
            $this->riyadh('2026-09-13 10:00'),   // Sunday, an hour in
        );

        // Friday and Saturday are closed in this schedule.
        $this->assertSame(120, $minutes);
    }

    public function test_a_full_working_day_is_eight_hours(): void
    {
        $minutes = $this->calculator()->workingMinutesBetween(
            $this->riyadh('2026-09-06 00:00'),
            $this->riyadh('2026-09-06 23:59'),
        );

        $this->assertSame(480, $minutes);
    }

    public function test_a_holiday_does_not_count(): void
    {
        $minutes = $this->calculator(['sla.holidays' => ['2026-09-07']])
            ->workingMinutesBetween(
                $this->riyadh('2026-09-06 16:00'),  // Sunday, an hour left
                $this->riyadh('2026-09-08 10:00'),  // Tuesday, an hour in
            );

        // Monday was a holiday. Without the holiday list this would be 540.
        $this->assertSame(120, $minutes);
    }

    public function test_a_backwards_interval_is_zero_not_negative(): void
    {
        $minutes = $this->calculator()->workingMinutesBetween(
            $this->riyadh('2026-09-06 12:00'),
            $this->riyadh('2026-09-06 10:00'),
        );

        /*
         * A caller asking about a backwards interval has a bug. A negative
         * elapsed time would silently make a breached ticket look on track,
         * which is the failure this whole engine exists to prevent.
         */
        $this->assertSame(0, $minutes);
    }

    public function test_a_week_with_every_day_closed_elapses_nothing(): void
    {
        $closed = array_fill_keys(['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'], null);

        $minutes = $this->calculator(['sla.working_hours' => $closed])
            ->workingMinutesBetween(
                $this->riyadh('2026-09-06 09:00'),
                $this->riyadh('2026-09-20 17:00'),
            );

        // "We are shut" is a legitimate thing to configure, and nothing should
        // breach while it is true.
        $this->assertSame(0, $minutes);
    }

    public function test_adding_working_minutes_lands_inside_the_same_day(): void
    {
        $deadline = $this->calculator()->addWorkingMinutes($this->riyadh('2026-09-06 10:00'), 120);

        $this->assertSame('2026-09-06 12:00', $deadline->setTimezone('Asia/Riyadh')->format('Y-m-d H:i'));
    }

    public function test_adding_working_minutes_rolls_over_the_night(): void
    {
        // Four working hours from 16:00 Sunday: one today, three tomorrow.
        $deadline = $this->calculator()->addWorkingMinutes($this->riyadh('2026-09-06 16:00'), 240);

        $this->assertSame('2026-09-07 12:00', $deadline->setTimezone('Asia/Riyadh')->format('Y-m-d H:i'));
    }

    public function test_adding_working_minutes_skips_the_weekend(): void
    {
        // Thursday 16:00 + 4 working hours: one hour Thursday, three Sunday.
        $deadline = $this->calculator()->addWorkingMinutes($this->riyadh('2026-09-10 16:00'), 240);

        $this->assertSame('2026-09-13 12:00', $deadline->setTimezone('Asia/Riyadh')->format('Y-m-d H:i'));
    }

    public function test_a_deadline_from_outside_hours_starts_at_opening(): void
    {
        // Raised at 02:00; the clock starts when the desk does.
        $deadline = $this->calculator()->addWorkingMinutes($this->riyadh('2026-09-06 02:00'), 60);

        $this->assertSame('2026-09-06 10:00', $deadline->setTimezone('Asia/Riyadh')->format('Y-m-d H:i'));
    }

    public function test_it_returns_utc(): void
    {
        $deadline = $this->calculator()->addWorkingMinutes($this->riyadh('2026-09-06 10:00'), 60);

        // Everything is stored and compared in UTC; only the formatting layer
        // converts. A local time leaking out of here is how a deadline moves by
        // three hours without anybody changing a number.
        $this->assertSame('UTC', $deadline->timezone->getName());
    }

    public function test_it_knows_when_the_desk_is_open(): void
    {
        $calculator = $this->calculator();

        $this->assertTrue($calculator->isOpenAt($this->riyadh('2026-09-06 10:00')));
        $this->assertFalse($calculator->isOpenAt($this->riyadh('2026-09-06 08:00')));
        $this->assertFalse($calculator->isOpenAt($this->riyadh('2026-09-11 10:00'))); // Friday
    }

    public function test_the_schedule_is_read_in_its_own_timezone(): void
    {
        // The same wall-clock schedule, read as UTC instead of Riyadh, moves
        // every boundary by three hours. This is the setting that says which.
        $utcCalculator = $this->calculator(['sla.timezone' => 'UTC']);

        $this->assertTrue($utcCalculator->isOpenAt(CarbonImmutable::parse('2026-09-06 10:00', 'UTC')));
        $this->assertFalse($utcCalculator->isOpenAt(CarbonImmutable::parse('2026-09-06 07:00', 'UTC')));
    }
}
