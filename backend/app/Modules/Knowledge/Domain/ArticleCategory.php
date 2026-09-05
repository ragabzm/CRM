<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Domain;

use Illuminate\Database\Eloquent\Model;

/**
 * An article category. Flat — see the migration for why there is no parent.
 *
 * Deliberately not `Tickets\Domain\Category`: see the migration.
 *
 * @property int $id
 * @property string $name_en
 * @property string $name_ar
 * @property int $sort_order
 */
final class ArticleCategory extends Model
{
    protected $table = 'article_categories';

    /** @var list<string> */
    protected $fillable = ['name_en', 'name_ar', 'sort_order'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }
}
