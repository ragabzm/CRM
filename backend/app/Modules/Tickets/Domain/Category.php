<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain;

use Illuminate\Database\Eloquent\Model;

/**
 * A ticket category. Flat — see the migration for why there is no parent.
 *
 * @property int $id
 * @property string $name_en
 * @property string $name_ar
 * @property int $sort_order
 */
final class Category extends Model
{
    protected $table = 'ticket_categories';

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
