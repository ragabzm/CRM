<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain\Outbound;

use App\Modules\Channels\Contracts\ChannelTransport;
use App\Modules\Channels\Contracts\ChannelTransportFailure;
use App\Modules\Channels\Infrastructure\NullChannelTransport;
use App\Modules\Customers\Domain\ContactKind;
use App\Modules\Customers\Domain\IdentifierNormaliser;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;

/**
 * Sends one reply on the channel its ticket arrived on.
 *
 * ONE path for WhatsApp and SMS. Which transport it picks is a lookup in the
 * channel account's `provider_config`; nothing below branches on the channel.
 *
 * The number is normalised to E.164 on the way OUT as well as in. A customer
 * whose number was stored with a country code and whose WhatsApp reported it
 * without one is the same person, and the reply has to reach them either way.
 */
final class ChannelSender
{
    public function __construct(private readonly Container $container) {}

    /**
     * @return array{provider_message_id: string}
     *
     * @throws ChannelTransportFailure
     */
    public function send(string $channel, ?string $channelAccountId, string $to, string $body): array
    {
        $account = $channelAccountId === null
            ? null
            : DB::table('channel_accounts')->where('id', $channelAccountId)->first();

        if ($account !== null && ! (bool) $account->is_active) {
            /*
             * Permanent, not temporary. An administrator switched this off;
             * retrying for ten minutes would neither change their mind nor
             * tell anybody. The composer already refuses before the agent
             * writes — this is the second line, for a message queued before
             * the channel was disabled.
             */
            throw ChannelTransportFailure::permanent(
                'The '.$channel.' channel is switched off.',
            );
        }

        $configuration = $this->configurationOf($account);

        return [
            'provider_message_id' => $this->transportFor($configuration)->send(
                // Normalised on the way out too. See the class note.
                IdentifierNormaliser::normalise(ContactKind::Phone, $to),
                $body,
                $configuration,
            ),
        ];
    }

    /**
     * Whether the configured provider can receive as well as send.
     *
     * Read by the channel configuration so an administrator learns that a
     * send-only provider is send-only when they set it up — rather than when a
     * customer's reply vanishes and nobody can say where it went.
     *
     * @param  array<string, mixed>  $configuration
     */
    public function supportsInbound(array $configuration): bool
    {
        return $this->transportFor($configuration)->supportsInbound();
    }

    /**
     * The transport for a provider name, defaulting to the null one.
     *
     * The DEFAULT matters: an account with no provider configured yet, or one
     * naming a provider this deployment does not ship, sends nothing and
     * records that it sent nothing. It does not throw at resolution time,
     * because that would take out the ticket screen for a configuration
     * mistake in a channel nobody is using.
     *
     * @param  array<string, mixed>  $configuration
     */
    private function transportFor(array $configuration): ChannelTransport
    {
        $provider = (string) ($configuration['provider'] ?? 'null');

        foreach ($this->container->tagged('channel.transports') as $transport) {
            /** @var ChannelTransport $transport */
            if ($transport->provider() === $provider) {
                return $transport;
            }
        }

        return $this->container->make(NullChannelTransport::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function configurationOf(?object $account): array
    {
        if ($account === null) {
            return [];
        }

        $decoded = json_decode((string) $account->provider_config, true);

        return is_array($decoded) ? $decoded : [];
    }
}
