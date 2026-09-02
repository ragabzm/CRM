<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AssignTicketRequest extends FormRequest
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
            // Present but null returns the ticket to the pool, which is a real
            // instruction and not the same as omitting the field.
            'assignee_id' => ['present', 'nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }
}
