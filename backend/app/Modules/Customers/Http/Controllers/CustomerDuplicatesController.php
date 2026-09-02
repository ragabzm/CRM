<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Domain\DuplicateDetector;
use App\Modules\Customers\Http\Requests\PreviewDuplicatesRequest;
use Illuminate\Http\JsonResponse;

/**
 * Checks for existing customers before the form is submitted.
 *
 * Read-only and side-effect free: the whole point is to show the agent who
 * already exists while they can still choose to open that record instead.
 */
final class CustomerDuplicatesController extends Controller
{
    public function __construct(private readonly DuplicateDetector $detector) {}

    /**
     * @response array{matches: array<int, array{customer_id:string,reference:string,full_name:string,state:string,matched_value:string,matched_kind:string}>}
     */
    public function preview(PreviewDuplicatesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return new JsonResponse([
            'matches' => $this->detector->preview(
                array_values((array) ($validated['emails'] ?? [])),
                array_values((array) ($validated['phones'] ?? [])),
                isset($validated['exclude_customer_id']) ? (string) $validated['exclude_customer_id'] : null,
            ),
        ]);
    }
}
