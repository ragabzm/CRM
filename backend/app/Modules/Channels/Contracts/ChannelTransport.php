<?php

declare(strict_types=1);

namespace App\Modules\Channels\Contracts;

/**
 * How this application hands a message to something that will deliver it.
 *
 * ONE port for WhatsApp and SMS both, and that is the story's own claim made
 * structural: the two channels share this method, the send job behind it, the
 * retry policy and the delivery-state vocabulary. What differs between them is
 * a row in `channel_accounts` — a provider name and some credentials — not a
 * code path. `OneTransportShapeTest` fails if a second one appears.
 *
 * A port, so the provider is a configuration value rather than a dependency,
 * and so CI needs no provider at all. `NullChannelTransport` is the default
 * and records what it was asked to send, which is what lets every test about
 * retries, delivery states and threading run without a network.
 *
 * Primitives only: this contract is read by modules that must not learn the
 * Channels module's domain types.
 */
interface ChannelTransport
{
    /**
     * Which provider this adapter speaks to, as stored on the channel account.
     */
    public function provider(): string;

    /**
     * Delivers one message, or throws.
     *
     * Throwing is the contract. A transport returning false would let a caller
     * ignore it with a discarded return value; an exception carries the
     * provider's own diagnosis into the log and into the retry decision.
     *
     * @param  string  $to      The recipient, already normalised to E.164.
     * @param  array<string, mixed>  $configuration  The channel account's provider_config.
     * @return string  The provider's own id for the message, where it gives
     *                 one. Empty when it does not — never invented, because a
     *                 fabricated id makes a delivery receipt unmatchable.
     *
     * @throws ChannelTransportFailure
     */
    public function send(string $to, string $body, array $configuration): string;

    /**
     * Whether this provider can receive as well as send.
     *
     * Stated in the channel configuration, so an administrator discovers a
     * send-only provider when they configure it — rather than when a
     * customer's reply vanishes and nobody can say where it went.
     */
    public function supportsInbound(): bool;
}
