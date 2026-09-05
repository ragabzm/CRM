<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Domain\Enum;

/**
 * Where an article stands. Three states, and there is no fourth.
 *
 * Draft → Published → Archived, and back to Published from Archived.
 *
 * There is deliberately no `in_review`. A review state without a reviewer, a
 * queue and an approval step is a state articles enter and never leave, and
 * this product has no workflow engine to give it those. Somebody authorised to
 * publish publishes.
 *
 * `Archived` is also what stands in for deleting a published article: see
 * `ArticleLifecycle`.
 */
enum ArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
