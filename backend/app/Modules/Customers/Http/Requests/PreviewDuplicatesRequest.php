<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PreviewDuplicatesRequest extends FormRequest
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
            'emails' => ['sometimes', 'array', 'max:20'],
            'emails.*' => ['string', 'max:200'],
            'phones' => ['sometimes', 'array', 'max:20'],
            'phones.*' => ['string', 'max:200'],
            // Set when checking an EXISTING customer's edits, so they are not
            // offered as their own duplicate.
            'exclude_customer_id' => ['sometimes', 'string', 'size:26'],
        ];
    }
}
