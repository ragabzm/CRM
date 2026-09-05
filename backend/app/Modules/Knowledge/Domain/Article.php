<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Domain;

use App\Modules\Knowledge\Domain\Enum\ArticleStatus;
use App\Modules\Knowledge\Domain\Enum\ArticleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An article, minus its words. They live in `translations`.
 *
 * @property string $id
 * @property ArticleType $type
 * @property int $category_id
 * @property bool $internal_only
 * @property ArticleStatus $status
 * @property string $default_locale
 * @property bool $has_been_published
 */
final class Article extends Model
{
    use HasUlids;

    protected $table = 'articles';

    /**
     * `has_been_published`, `status` and the four lifecycle stamps are ABSENT
     * on purpose.
     *
     * They are set by `ArticleLifecycle` and nowhere else. Making them fillable
     * would let a request body publish an article by naming a field — and
     * would let one clear `has_been_published`, which is the flag standing
     * between a published article and permanent deletion.
     *
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'category_id',
        'internal_only',
        'default_locale',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ArticleType::class,
            'status' => ArticleStatus::class,
            'internal_only' => 'boolean',
            'has_been_published' => 'boolean',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ArticleTranslation, $this>
     */
    public function translations(): HasMany
    {
        return $this->hasMany(ArticleTranslation::class);
    }

    /**
     * @return BelongsTo<ArticleCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ArticleCategory::class, 'category_id');
    }

    /**
     * The articles a customer may see.
     *
     * A QUERY scope, not a check in a resource or a filter in the interface.
     * Visibility decided at render is visibility that leaks the first time
     * somebody writes a second read path — a count, an export, a search — and
     * forgets it. Here, an internal article is not in the result set at all.
     *
     * @param  Builder<Article>  $query
     */
    public static function scopeCustomerVisible(Builder $query): void
    {
        $query
            ->where('internal_only', false)
            ->where('status', ArticleStatus::Published->value);
    }

    /** Public and published. The two conditions, together, and nothing else. */
    public function isCustomerVisible(): bool
    {
        return ! $this->internal_only && $this->status === ArticleStatus::Published;
    }

    /** Never published means never seen; only then may it be deleted. */
    public function canBeDeleted(): bool
    {
        return ! $this->has_been_published;
    }
}
