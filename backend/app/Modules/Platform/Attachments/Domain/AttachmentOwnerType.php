<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Domain;

/**
 * The things an attachment can hang off.
 *
 * A closed set, not an open string. Polymorphism without a fixed vocabulary is
 * how a table ends up holding `Customer`, `customer` and `App\Models\Customer`
 * as three different owners of the same record.
 */
enum AttachmentOwnerType: string
{
    case Customer = 'customer';
    case Ticket = 'ticket';
    case Message = 'message';

    /**
     * A public web-form submission that has not been sent yet.
     *
     * The only owner that is not a record in this system: it is the short-lived
     * token issued when the form is drawn. `InboundIntake` re-owns the file
     * onto the message the moment the ticket exists, so a draft owner is a
     * transient state rather than a fourth kind of thing that owns files.
     */
    case WebFormDraft = 'web_form_draft';

    /**
     * A knowledge article.
     *
     * Same subsystem as every other attachment — the same allow-list, the same
     * scan, the same quarantine, the same short-lived signed URL. A screenshot
     * on a help article is not a different kind of file from a screenshot on a
     * ticket, and giving it a second pipeline would be giving it a second set
     * of bugs.
     */
    case Article = 'article';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
