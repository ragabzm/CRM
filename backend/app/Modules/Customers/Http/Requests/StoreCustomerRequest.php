<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Customers\Domain\ContactKind;
use App\Modules\Customers\Domain\PreferredChannelRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The route's capability middleware already decided this.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'min:1', 'max:200'],
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')],
            'preferred_channel' => ['nullable', Rule::in(ContactKind::values())],
            'notes' => ['nullable', 'string', 'max:10000'],

            /*
             * At least one way to reach them. A customer record with no contact
             * details cannot be replied to, which makes it a name in a list
             * rather than a customer.
             */
            'identifiers' => ['required', 'array', 'min:1'],
            'identifiers.*.kind' => ['required', Rule::in(ContactKind::values())],
            'identifiers.*.value' => ['required', 'string', 'min:1', 'max:200'],
            'identifiers.*.is_primary' => ['sometimes', 'boolean'],

            // The client's acknowledgement of an offered duplicate.
            'confirm_create_duplicate' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identifiers.required' => 'Add at least one email address or phone number.',
            'identifiers.min' => 'Add at least one email address or phone number.',
            'department_id.required' => 'Choose a department. It groups the customer; it does not restrict who can see them.',
        ];
    }

    public function withValidator(mixed $validator): void
    {
        PreferredChannelRule::attach($validator, $this->input('preferred_channel'), $this->input('identifiers'));
    }
}
