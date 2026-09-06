<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Actor;

use InvalidArgumentException;

/**
 * The application acting on its own behalf.
 *
 * Carries a REASON rather than an id, and the reason is required. "The system
 * closed it" is not an explanation anybody can act on; "scheduler:auto_close"
 * tells whoever is reading the history which job to go and look at.
 */
final class SystemActor extends Actor
{
    public function __construct(
        public readonly string $why,
        /**
         * What the transcript calls it.
         *
         * Defaults to "System", which is right for a sweep or a job nobody
         * needs to picture. The chatbot passes its own, because a customer
         * reading a conversation has to be able to tell that the thing which
         * answered them was not a person — and that has to survive into the
         * ticket a colleague reads afterwards.
         *
         * A machine token, not a translated word: the transcript is read in
         * both languages and stored once.
         */
        private readonly string $as = 'System',
    ) {
        if (trim($why) === '') {
            /*
             * Refused at construction, not at write time. An event reading
             * "the system did it" with no reason is a dead end for whoever is
             * trying to explain a change months later — and by then there is
             * nothing left to reconstruct it from.
             */
            throw new InvalidArgumentException(
                'A system actor must carry a reason, e.g. "auto_close" or "sla_breach".'
            );
        }
    }

    public function kind(): string
    {
        return 'system';
    }

    public function id(): ?string
    {
        return null;
    }

    public function label(): string
    {
        return $this->as;
    }

    public function reason(): ?string
    {
        return $this->why;
    }
}
