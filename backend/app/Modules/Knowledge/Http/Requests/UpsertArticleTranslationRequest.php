<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * One article in one language.
 *
 * The body is validated as a string and nothing more. Trying to validate the
 * SHAPE of HTML here would be a second, weaker copy of the sanitiser — and the
 * two would disagree, which means either a body the validator accepts and the
 * sanitiser empties, or one the validator rejects that was perfectly safe.
 * `HtmlSanitiser` is the single authority, and it runs on write.
 */
final class UpsertArticleTranslationRequest extends FormRequest
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
            'title' => ['required', 'string', 'min:1', 'max:200'],
            'body' => ['required', 'string', 'max:200000'],
        ];
    }
}
