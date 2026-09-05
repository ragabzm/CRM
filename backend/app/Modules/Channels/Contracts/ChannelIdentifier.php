<?php

declare(strict_types=1);

namespace App\Modules\Channels\Contracts;

/**
 * How a channel names the person who wrote in.
 *
 * An email address, a phone number, and later a WhatsApp number or a chat
 * session — all of them are a `kind` plus a `value`, and all of them are
 * matched against the same `contact_identifiers` table. Modelling them as one
 * thing is what lets a customer who emails on Monday and fills in the form on
 * Tuesday be one customer rather than two.
 */
final readonly class ChannelIdentifier
{
    public function __construct(
        /**
         * The `ContactKind` value — `email` or `phone` — as its string.
         *
         * A string rather than the enum because this is a contract, and a
         * contract that imports another module's enum hands that dependency to
         * every adapter that implements it. The spine converts once, at the
         * edge, where it already depends on Customers.
         */
        public string $kind,
        public string $value,
        /** Whatever the channel knows them as. May be empty. */
        public string $displayName = '',
    ) {}
}
