<?php

declare(strict_types=1);

namespace App\Modules\Platform\Audit\Domain;

/**
 * Who did it, in the broadest sense.
 *
 * The three values mirror `RequestContext::ACTOR_TYPES` exactly, and a test
 * asserts they still agree. Two vocabularies for one concept is how a log ends
 * up recording `anonymous` while the correlated log line for the same request
 * says `guest`, leaving whoever is joining them to guess whether those are the
 * same thing.
 */
enum AuditActorType: string
{
    /** A signed-in person. */
    case User = 'user';

    /** The application acting on its own behalf — a job, a command, a webhook. */
    case Service = 'service';

    /** Nobody identified. A failed sign-in is the common case. */
    case Guest = 'guest';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
