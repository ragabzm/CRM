<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain\Intake;

use App\Modules\Channels\Contracts\ChannelIdentifier;
use App\Modules\Customers\Contracts\CustomerDirectory;

/**
 * Who wrote this, as a customer record.
 *
 * When nobody matches, a record is CREATED rather than the message being
 * refused. A support desk that only accepts messages from people already in the
 * database is a support desk that cannot be contacted.
 *
 * Both halves go through `CustomerDirectory`. The Email module used to insert
 * into the `customers` and `contact_identifiers` tables itself, which meant it
 * knew the reference format and the address-normalisation rule — two things
 * that belong to Customers and that would have broken it silently the day
 * either changed. The same applies to every channel that follows, which is why
 * the resolver is here and not repeated in each adapter.
 */
final class CustomerResolver
{
    public function __construct(private readonly CustomerDirectory $customers) {}

    /**
     * @return array{id: string, created: bool}
     */
    public function resolve(string $channel, ChannelIdentifier $identifier): array
    {
        /*
         * One call, whatever the kind. The Directory owns the whole identity
         * question — including the case that is easy to miss, where the same
         * digits arrive on a channel we had not seen this person use. Deciding
         * that here would mean every future transport deciding it again, and
         * one of them getting it wrong.
         */
        return $this->customers->resolveOrCreate(
            $identifier->kind,
            $identifier->value,
            $identifier->displayName,
            'inbound_'.$channel,
        );
    }
}
