<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Requests;

use App\Modules\Security\Domain\Department;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateDepartmentRequest extends FormRequest
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
        /** @var Department|null $department */
        $department = $this->route('department');

        return [
            'name' => ['required', 'string', 'min:1', 'max:120', new UniqueDepartmentName($department?->getKey())],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
