<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Domain\Enum;

/**
 * A language an article can be written in.
 *
 * The set the product ships, not a general locale type: adding a third is a
 * row in `article_translations` and one case here, with no migration and no
 * column. That is the whole reason translations are rows.
 */
enum ArticleLocale: string
{
    case En = 'en';
    case Ar = 'ar';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
