<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Requests;

use App\Modules\Security\Domain\Roles;
use App\Modules\Security\Rules\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // The capability middleware already refused anyone else.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(Roles::all())],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
            'is_active' => ['sometimes', 'boolean'],
            // Optional: omitted means the account is created without a usable
            // password and the person sets one through the reset flow, which is
            // better than an administrator inventing and transmitting one.
            'password' => ['sometimes', 'nullable', 'string', 'confirmed', new PasswordPolicy],
        ];
    }
}
