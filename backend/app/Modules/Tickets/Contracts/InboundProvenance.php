<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Contracts;

/**
 * How Tickets asks "where did this message come from, and why is it here?".
 *
 * Declared HERE and implemented by Channels, for the same reason `SlaReader`
 * and `ChannelAvailability` are: Channels is T4 and Tickets is T3, so the
 * dependency has to point downward.
 *
 * The question it answers is the one a mis-correlated message raises. An agent
 * looking at a reply that landed on the wrong ticket can otherwise only ask
 * somebody to check the database — and by then the message that would explain
 * it has been read and forgotten.
 *
 * Primitives only, so no caller learns a domain type.
 */
interface InboundProvenance
{
    /**
     * How these messages arrived, keyed by message id.
     *
     * Batched rather than one at a time: a conversation renders every message
     * at once, and a per-message call would be the N+1 this signature exists to
     * prevent. Messages that did not arrive through a channel — an agent's own
     * reply, an internal note — are simply absent from the result.
     *
     * @param  list<string>  $messageIds
     * @return array<string, array{channel: string, correlation_reason: string|null}>
     */
    public function forMessages(array $messageIds): array;
}
