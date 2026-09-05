<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Domain\Enum;

/**
 * What kind of answer this is.
 *
 * A LABEL, and nothing more. It changes no permission, no delivery path and no
 * surface — an FAQ and a Guide are read, published and archived identically.
 * It exists so somebody scanning a list of four hundred articles can filter to
 * the twelve they meant.
 *
 * Written down here because the temptation, the first time a type needs to
 * behave differently, is to branch on it. That is a new story, not a quiet
 * change to this file.
 */
enum ArticleType: string
{
    case Faq = 'faq';
    case Help = 'help';
    case Solution = 'solution';
    case Guide = 'guide';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
