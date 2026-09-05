<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Http\Requests;

use App\Modules\Knowledge\Domain\Enum\ArticleLocale;
use App\Modules\Knowledge\Domain\Enum\ArticleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Editing an article's attributes.
 *
 * Refuses the lifecycle fields OUT LOUD rather than ignoring them. A request
 * that sends `status: published` and gets a 200 back has been told its edit
 * worked; discovering later that the article is still a draft is worse than
 * being refused, and it is the kind of thing a client keeps doing.
 */
final class UpdateArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Fields only the lifecycle may set. */
    private const LIFECYCLE_ONLY = [
        'status',
        'has_been_published',
        'published_at',
        'published_by',
        'archived_at',
        'archived_by',
    ];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::in(ArticleType::values())],
            'category_id' => ['sometimes', 'integer', 'exists:article_categories,id'],
            'default_locale' => ['sometimes', Rule::in(ArticleLocale::values())],
            'internal_only' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (self::LIFECYCLE_ONLY as $field) {
                if ($this->has($field)) {
                    $validator->errors()->add(
                        $field,
                        'This is set by publishing or archiving, not by editing.',
                    );
                }
            }
        });
    }
}
