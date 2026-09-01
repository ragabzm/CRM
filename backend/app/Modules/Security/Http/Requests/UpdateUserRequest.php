<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Requests;

use App\Modules\Security\Domain\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
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
        /** @var \App\Models\User|null $user */
        $user = $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'min:1', 'max:120'],
            'email' => [
                'sometimes',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->getKey()),
            ],
            'role' => ['sometimes', Rule::in(Roles::all())],
            'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists('departments', 'id')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
