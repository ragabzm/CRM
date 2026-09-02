<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Tickets\Domain\Priority;
use Illuminate\Http\JsonResponse;

/**
 * The four priorities, read-only.
 *
 * There is no write endpoint, and its absence is deliberate: priority is a
 * vocabulary the whole product reasons about — SLA targets are keyed on it, the
 * queue sorts by it — so a fifth value invented at runtime would have no target
 * and no sort position. The console SAYS they are fixed rather than omitting
 * the section, because an administrator who cannot find priorities concludes
 * the product has none.
 */
final class PrioritiesController extends Controller
{
    /**
     * @response array{data: array<int, array{value:string}>, editable: bool}
     */
    public function index(): JsonResponse
    {
        return new JsonResponse([
            'data' => array_map(
                static fn (string $value): array => ['value' => $value],
                Priority::values(),
            ),
            // Stated in the payload as well as the copy, so a client cannot
            // render an edit affordance by accident.
            'editable' => false,
        ]);
    }
}
