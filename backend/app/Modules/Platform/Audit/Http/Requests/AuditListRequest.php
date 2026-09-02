<?php

declare(strict_types=1);

namespace App\Modules\Platform\Audit\Http\Requests;

use App\Modules\Platform\Audit\Application\AuditQuery;
use App\Modules\Platform\Audit\Domain\AuditAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the three filters, and only the three.
 *
 * Authorization is not decided here — the route's capability middleware has
 * already refused anyone without `audit.read`. A second check in this class
 * would be a second place to get it wrong.
 */
final class AuditListRequest extends FormRequest
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
            'actor_id' => ['sometimes', 'string', 'max:64'],
            'actor_search' => ['sometimes', 'string', 'max:255'],
            'action' => ['sometimes', 'string', Rule::in(AuditAction::values())],
            'from' => ['sometimes', 'date_format:Y-m-d'],
            'to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.AuditQuery::MAX_PER_PAGE],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            // Says which timezone, because "entries on the 1st" means different
            // rows depending on the answer and the reader cannot see ours.
            'from.date_format' => 'Provide `from` as a YYYY-MM-DD date. Dates are inclusive and read in UTC.',
            'to.date_format' => 'Provide `to` as a YYYY-MM-DD date. Dates are inclusive and read in UTC.',
            'to.after_or_equal' => 'The end of the range cannot fall before its start.',
            'action.in' => 'Filter by one of the recorded actions.',
        ];
    }
}
