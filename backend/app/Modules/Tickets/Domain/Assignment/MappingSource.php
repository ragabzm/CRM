<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Assignment;

/**
 * What a mapping is keyed on.
 *
 * Two, and their ORDER here is the precedence: category beats department.
 * That is fixed in code and deliberately not configurable — a configurable
 * precedence is an ordering, an ordering needs a way to see which rule won,
 * and that is the rule engine this story is not.
 *
 * The reason category wins: a department is where a ticket lands by default,
 * while a category is something somebody chose about this particular ticket.
 * The more specific statement should not lose to the general one.
 */
enum MappingSource: string
{
    case Category = 'category';
    case Department = 'department';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * Highest precedence first. `cases()` order IS the precedence.
     *
     * @return list<self>
     */
    public static function inPrecedenceOrder(): array
    {
        return self::cases();
    }
}
