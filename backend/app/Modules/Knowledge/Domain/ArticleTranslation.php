<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Domain;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One article in one language.
 *
 * `body` is HTML that has already been through `HtmlSanitiser`. Nothing writes
 * this column directly — the sanitiser is the only door, and it is on the write
 * path rather than the read path so that a body stored before a rule changed
 * cannot slip past a reader who arrives after it.
 *
 * @property string $id
 * @property string $article_id
 * @property string $locale
 * @property string $title
 * @property string $body
 */
final class ArticleTranslation extends Model
{
    use HasUlids;

    protected $table = 'article_translations';

    /** @var list<string> */
    protected $fillable = ['article_id', 'locale', 'title', 'body'];

    /**
     * @return BelongsTo<Article, $this>
     */
    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
