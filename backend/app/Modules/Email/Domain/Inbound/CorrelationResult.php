<?php

declare(strict_types=1);

namespace App\Modules\Email\Domain\Inbound;

/**
 * Which ticket an inbound email belongs to, and the reasoning.
 *
 * The trace travels with the answer rather than being logged separately,
 * because the two are only useful together: knowing a message landed on ticket
 * X is not the same as being able to explain why.
 */
final readonly class CorrelationResult
{
    /**
     * @param  string|null  $ticketId  Null means "no existing ticket — open one".
     * @param  list<array{rule: string, matched: bool}>  $trace
     */
    public function __construct(
        public ?string $ticketId,
        public string $winningRule,
        public array $trace,
    ) {}

    public function isReply(): bool
    {
        return $this->ticketId !== null;
    }
}
