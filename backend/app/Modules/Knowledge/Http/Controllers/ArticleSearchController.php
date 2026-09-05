<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Knowledge\Domain\Search\ArticleSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Staff search: everything, internal included.
 *
 * The in-ticket panel calls this on every debounced keystroke, from the
 * busiest screen in the product. It answers with the least it can — an id, a
 * title and enough to render a chip — because the panel shows a list and the
 * body is only wanted when somebody opens one.
 */
final class ArticleSearchController extends Controller
{
    public function __construct(private readonly ArticleSearch $search) {}

    /**
     * @response array{data: array<int, array<string, mixed>>}
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:200'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.ArticleSearch::MAX_LIMIT],
        ]);

        return new JsonResponse([
            'data' => $this->search->search(
                (string) $validated['q'],
                app()->getLocale(),
                // Staff see internal articles. That is what internal articles
                // are for.
                customerVisibleOnly: false,
                limit: (int) ($validated['limit'] ?? ArticleSearch::DEFAULT_LIMIT),
            ),
        ]);
    }
}
