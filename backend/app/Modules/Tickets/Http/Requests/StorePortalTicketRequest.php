<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * What a customer may say when opening a ticket.
 *
 * Deliberately short. No `customer_id` (taken from the account), no `channel`
 * (fixed to portal), no `priority`, `assignee_id` or `department_id` — a
 * customer marking their own ticket Urgent and assigning it to a named agent
 * would make those fields mean nothing.
 */
final class StorePortalTicketRequest extends FormRequest
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
        ];
    }
}
