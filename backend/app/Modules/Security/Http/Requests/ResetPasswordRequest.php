<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Requests;

use App\Modules\Security\Rules\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

final class ResetPasswordRequest extends FormRequest
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
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'password' => ['required', 'string', 'confirmed', new PasswordPolicy],
        ];
    }
}
