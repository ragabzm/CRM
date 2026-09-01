<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'min:1', 'max:120'],
            // The two locales the product ships. An unknown value is a 422
            // rather than a silent fallback, so a typo surfaces at the source.
            'preferred_locale' => ['sometimes', Rule::in(['en', 'ar'])],
        ];
    }
}
