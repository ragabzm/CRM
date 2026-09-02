<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Customers\Domain\ContactKind;
use App\Modules\Customers\Domain\PreferredChannelRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Every field optional; supplying `identifiers` replaces the whole set.
 *
 * Replacement rather than merge, because the form the agent is looking at shows
 * the complete list — the rows they deleted are gone from what they submit, and
 * a merge would silently resurrect them.
 */
final class UpdateCustomerRequest extends FormRequest
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
            'full_name' => ['sometimes', 'string', 'min:1', 'max:200'],
            'department_id' => ['sometimes', 'integer', Rule::exists('departments', 'id')],
            'preferred_channel' => ['sometimes', 'nullable', Rule::in(ContactKind::values())],
            'notes' => ['sometimes', 'nullable', 'string', 'max:10000'],

            'identifiers' => ['sometimes', 'array', 'min:1'],
            'identifiers.*.kind' => ['required_with:identifiers', Rule::in(ContactKind::values())],
            'identifiers.*.value' => ['required_with:identifiers', 'string', 'min:1', 'max:200'],
            'identifiers.*.is_primary' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'identifiers.min' => 'A customer needs at least one email address or phone number.',
        ];
    }

    public function withValidator(mixed $validator): void
    {
        if ($this->has('preferred_channel') && $this->has('identifiers')) {
            PreferredChannelRule::attach($validator, $this->input('preferred_channel'), $this->input('identifiers'));
        }
    }
}
