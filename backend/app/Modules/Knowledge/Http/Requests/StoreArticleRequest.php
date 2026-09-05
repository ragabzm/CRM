<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Http\Requests;

use App\Modules\Knowledge\Domain\Enum\ArticleLocale;
use App\Modules\Knowledge\Domain\Enum\ArticleType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The attributes of a new article. Not its words, and not its state.
 *
 * `status`, `has_been_published` and the lifecycle stamps are absent by
 * construction: they belong to `ArticleLifecycle`, and a request that could
 * name them could publish an article without going through the rule that makes
 * publishing permanent.
 */
final class StoreArticleRequest extends FormRequest
{
    /** The route's capability middleware has already decided. */
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
            'type' => ['required', Rule::in(ArticleType::values())],
            'category_id' => ['required', 'integer', 'exists:article_categories,id'],
            'default_locale' => ['required', Rule::in(ArticleLocale::values())],
            'internal_only' => ['sometimes', 'boolean'],
        ];
    }
}
