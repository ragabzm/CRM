<?php

declare(strict_types=1);

namespace App\Modules\Sla\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Sla\Domain\WorkingHoursCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Four working hours from now is Tuesday at 11:00."
 *
 * A target in seconds is not something a person can picture. An administrator
 * setting "8 hours" against a Sunday-to-Thursday, 09:00–17:00 week has to work
 * out in their head that it means the next working day — and the schedule they
 * are working against is the one they are also editing on the same screen.
 *
 * Answered by the SAME calculator the timers use, so a preview cannot promise
 * something the engine will not deliver.
 */
final class SlaPreviewController extends Controller
{
    public function __construct(
        private readonly WorkingHoursCalculator $hours,
        private readonly SettingsRegistry $settings,
    ) {}

    /**
     * @response array{minutes: int, from: string, due_at: string, timezone: string}
     */
    public function show(Request $request): JsonResponse
    {
        $minutes = (int) $request->query('minutes', '0');

        if ($minutes < 1 || $minutes > 525_600) {
            throw ProblemException::make(
                'sla.invalid_preview',
                'That is not a target anyone could set',
                422,
                'Give a number of working minutes between 1 and a year.',
            );
        }

        // From now unless asked otherwise. `from` exists so the editor can
        // preview a target against a specific moment when explaining a case.
        $from = $request->query('from') === null
            ? CarbonImmutable::now('UTC')
            : CarbonImmutable::parse((string) $request->query('from'))->setTimezone('UTC');

        $due = $this->hours->addWorkingMinutes($from, $minutes);

        return new JsonResponse([
            'minutes' => $minutes,
            'from' => $from->toIso8601ZuluString(),
            // UTC, like everything else. The FORMATTING layer converts — that
            // is the one place a local time is allowed to appear.
            'due_at' => $due->toIso8601ZuluString(),
            // So the editor can name the zone it is showing, rather than
            // rendering a time the reader has to guess the zone of.
            'timezone' => (string) $this->settings->get('sla.timezone'),
        ]);
    }
}
