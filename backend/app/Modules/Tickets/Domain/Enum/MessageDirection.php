<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Enum;

/**
 * Which way a message went, or that it went nowhere.
 *
 * Inbound is from the customer, outbound is to them. Recorded rather than
 * inferred from the author, because a system-generated acknowledgement is
 * outbound with no person behind it.
 *
 * `Internal` is the third: a note colleagues leave for each other on the
 * ticket. It is not a direction in any honest sense — nothing travels — but it
 * lives in this column because "may the customer see this?" has to be ONE
 * question with one answer, on one indexed column. A separate `is_internal`
 * flag would mean every customer-facing query had to remember two conditions,
 * and the one that forgets is the one that emails a colleague's private note
 * to the customer it is about.
 */
enum MessageDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
    case Internal = 'internal';

    /**
     * Whether the customer may ever see this.
     *
     * The single question every outbound pipeline must ask. Expressed here, on
     * the enum, so that adding a fourth case forces whoever adds it to answer
     * it — rather than defaulting to visible, which is the failure that cannot
     * be taken back.
     */
    public function isCustomerVisible(): bool
    {
        return $this !== self::Internal;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
