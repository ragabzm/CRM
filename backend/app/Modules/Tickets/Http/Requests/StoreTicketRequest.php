<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Requests;

use App\Modules\Tickets\Domain\Enum\TicketChannel;
use App\Modules\Tickets\Domain\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTicketRequest extends FormRequest
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
            'subject' => ['required', 'string', 'min:1', 'max:200'],
            'description' => ['required', 'string', 'min:1', 'max:20000'],
            'customer_id' => ['required', 'string', 'size:26', Rule::exists('customers', 'id')],
            'channel' => ['required', Rule::in(TicketChannel::values())],
            'category_id' => ['nullable', 'integer', Rule::exists('ticket_categories', 'id')],
            'priority' => ['nullable', Rule::in(Priority::values())],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            // No `version` on create: there is nothing yet to be stale against.
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject.required' => 'Give the ticket a subject. It is what everyone sees in the queue.',
            'description.required' => 'Describe what happened.',
            'customer_id.exists' => 'That customer does not exist.',
        ];
    }
}
