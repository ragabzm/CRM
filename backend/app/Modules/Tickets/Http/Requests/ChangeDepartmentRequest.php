<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ChangeDepartmentRequest extends FormRequest
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
            /*
             * Existence is checked by the command, not by an `exists` rule, so
             * the refusal is `tickets.department_invalid` with the id echoed
             * back rather than a generic validation error the UI cannot act on.
             */
            'department_id' => ['required', 'integer'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }
}
