<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Requests;

use App\Modules\Tickets\Application\Timeline\CustomerTimelineQuery;
use App\Modules\Tickets\Application\Timeline\TimelineCursor;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Two parameters, and deliberately no more.
 *
 * No channel filter, no date range, no kind filter. This version of the
 * timeline is one list read top to bottom; a filter bar would need its own
 * indexes and its own empty states, and the story says explicitly that it is
 * out of scope.
 *
 * Unknown parameters are ignored rather than rejected, so a bookmarked URL from
 * a future version still renders rather than erroring.
 */
final class CustomerTimelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cursor' => ['sometimes', 'nullable', 'string', 'max:512'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:'.CustomerTimelineQuery::MAX_LIMIT],
        ];
    }

    public function cursor(): ?TimelineCursor
    {
        return TimelineCursor::decode($this->query('cursor'));
    }

    public function limit(): int
    {
        return (int) ($this->validated()['limit'] ?? CustomerTimelineQuery::DEFAULT_LIMIT);
    }
}
