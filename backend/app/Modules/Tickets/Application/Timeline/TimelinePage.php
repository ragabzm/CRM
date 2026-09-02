<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Application\Timeline;

final class TimelinePage
{
    /**
     * @param  list<TimelineEntry>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly ?string $nextCursor,
        public readonly bool $hasMore,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data' => array_map(static fn (TimelineEntry $e): array => $e->toArray(), $this->items),
            'next_cursor' => $this->nextCursor,
            'has_more' => $this->hasMore,
        ];
    }
}
