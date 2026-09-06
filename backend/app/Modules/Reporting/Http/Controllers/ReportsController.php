<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Domain\ReportPeriod;
use App\Modules\Reporting\Domain\SatisfactionReport;
use App\Modules\Reporting\Domain\SlaPerformance;
use App\Modules\Reporting\Domain\TicketVolume;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The whole reporting surface: one endpoint, one filter, three sections.
 *
 * READ-ONLY, and there is no write method here to become otherwise. Reporting
 * owns no table and performs no write — it reads facts recorded when they
 * happened, which is what makes a past period answer the same way twice.
 *
 * ONE REQUEST, not three. The three sections are one question asked about one
 * range, and splitting them would let a supervisor read a satisfaction rate
 * from one moment beside a volume from another — with nothing on screen saying
 * they disagree.
 *
 * There is no export. No CSV, no PDF, no print view, no download control and
 * no share link — not here, and not anywhere on the surface above.
 */
final class ReportsController extends Controller
{
    public function __construct(
        private readonly TicketVolume $volume,
        private readonly SlaPerformance $sla,
        private readonly SatisfactionReport $satisfaction,
    ) {}

    /**
     * @response array{data: array<string, mixed>}
     */
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
        ]);

        $period = ReportPeriod::between((string) $data['from'], (string) $data['to']);

        return new JsonResponse([
            'data' => [
                'period' => [
                    'from' => $period->from->toDateString(),
                    'to' => $period->to->toDateString(),
                ],
                'volume' => $this->volume->forPeriod($period),
                'sla' => $this->sla->forPeriod($period),
                'satisfaction' => $this->satisfaction->forPeriod($period),
            ],
        ]);
    }
}
