<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreDepartmentRequest extends FormRequest
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
            // max:120 counts CHARACTERS, not bytes, so an Arabic department
            // name is not silently shorter than an English one.
            'name' => ['required', 'string', 'min:1', 'max:120', new UniqueDepartmentName],
        ];
    }
}
