<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Priority;

/**
 * A partial change to a ticket's attributes.
 *
 * Every field is optional AND every field distinguishes "not supplied" from
 * "set to null" — `assignee_id: null` means unassign, which is a real
 * instruction, while omitting it means leave the assignee alone. A plain
 * nullable property could not tell those apart, so presence is tracked
 * explicitly.
 */
final class TicketAttributeChanges
{
    /** @param array<string, mixed> $changes */
    private function __construct(public readonly array $changes) {}

    /**
     * @param  array<string, mixed>  $validated  Only keys actually present.
     */
    public static function fromValidated(array $validated): self
    {
        $changes = [];

        if (array_key_exists('status', $validated)) {
            $changes['status'] = TicketStatus::from((string) $validated['status']);
        }

        if (array_key_exists('priority', $validated)) {
            $changes['priority'] = Priority::from((string) $validated['priority']);
        }

        if (array_key_exists('category_id', $validated)) {
            $changes['category_id'] = $validated['category_id'] === null ? null : (int) $validated['category_id'];
        }

        if (array_key_exists('assignee_id', $validated)) {
            $changes['assignee_id'] = $validated['assignee_id'] === null ? null : (int) $validated['assignee_id'];
        }

        if (array_key_exists('department_id', $validated)) {
            $changes['department_id'] = $validated['department_id'] === null ? null : (int) $validated['department_id'];
        }

        return new self($changes);
    }

    /** @param array<string, mixed> $changes */
    public static function of(array $changes): self
    {
        return new self($changes);
    }

    public function isEmpty(): bool
    {
        return $this->changes === [];
    }

    /** @return array<string, mixed> Column => value, ready for forceFill. */
    public function toColumns(): array
    {
        $columns = [];

        foreach ($this->changes as $key => $value) {
            $columns[$key] = $value instanceof \BackedEnum ? $value->value : $value;
        }

        return $columns;
    }
}
