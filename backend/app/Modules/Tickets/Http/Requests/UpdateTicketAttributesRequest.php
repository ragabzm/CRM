<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Requests;

use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A partial change. Every attribute optional, `version` required.
 *
 * `version` is required even though the guard would tolerate null, because a
 * caller who omitted it would be silently opting out of the protection this
 * whole mechanism exists for.
 */
final class UpdateTicketAttributesRequest extends FormRequest
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
            'version' => ['required', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(TicketStatus::values())],
            'priority' => ['sometimes', Rule::in(Priority::values())],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('ticket_categories', 'id')],
            'assignee_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists('departments', 'id')],
        ];
    }
}
