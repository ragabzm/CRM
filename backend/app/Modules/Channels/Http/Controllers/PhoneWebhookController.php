<?php

declare(strict_types=1);

namespace App\Modules\Channels\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Channels\Adapters\PhoneChannelAdapter;
use App\Modules\Channels\Domain\Intake\InboundIntake;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Enum\DeliveryState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Where a WhatsApp or SMS provider delivers a customer's message.
 *
 * ONE signed route per channel, verified BEFORE the payload is read — this is
 * a route, not the deferred webhook subsystem, and it deliberately does not
 * grow into one.
 *
 * The signature is checked first and in constant time. Everything after it
 * hands straight to the Story 7.1 spine: same idempotency key, same
 * identification, same correlation order, same quarantine, same department
 * rule. Neither channel gets a rule, a ticket type or an inbox of its own, and
 * `SameThroughEveryTransportTest` is what says so.
 *
 * Always answers 200 once the message is ours. A provider that receives a 500
 * retries — and retrying a message already quarantined would fill quarantine
 * with copies of it.
 */
final class PhoneWebhookController extends Controller
{
    public function __construct(private readonly InboundIntake $intake) {}

    /**
     * @response array{status: string}
     */
    public function store(Request $request, string $channel): JsonResponse
    {
        $this->assertKnownChannel($channel);

        $account = $this->assertSignature($request, $channel);

        $adapter = new PhoneChannelAdapter($channel);

        return new JsonResponse(
            $this->intake->accept($adapter, $request->all() + ['provider' => $this->providerOf($account)]),
            200,
        );
    }

    /**
     * A provider telling us what happened to something we sent.
     *
     * Separate from the inbound route because it is a different fact about a
     * different message. `delivered` and `read` are set HERE and nowhere else
     * — never inferred from a successful send, because "we handed it over" and
     * "it arrived" are different things and an agent reading "delivered" is
     * entitled to believe the second.
     *
     * @response array{status: string}
     */
    public function receipt(Request $request, string $channel): JsonResponse
    {
        $this->assertKnownChannel($channel);
        $this->assertSignature($request, $channel);

        $validated = $request->validate([
            'provider_message_id' => ['required', 'string', 'max:320'],
            'state' => ['required', 'string'],
        ]);

        $state = DeliveryState::tryFrom((string) $validated['state']);

        if ($state === null || ! in_array($state, [DeliveryState::Delivered, DeliveryState::Read, DeliveryState::Failed], true)) {
            /*
             * Only the states a receipt can legitimately report. A provider
             * claiming `queued` is describing our side of the exchange, not
             * theirs, and letting it write that would let a receipt move a
             * message backwards.
             */
            throw ProblemException::make(
                'channels.unknown_delivery_state',
                'Unrecognised delivery state',
                422,
                'A receipt may report delivered, read or failed.',
            );
        }

        $updated = DB::table('ticket_messages')
            ->where('provider_message_id', $validated['provider_message_id'])
            ->update(['delivery_state' => $state->value, 'updated_at' => now()]);

        /*
         * 200 either way, and the count says which happened. A receipt for a
         * message we do not have is not an error on the provider's side —
         * they are telling us about something that may predate this
         * deployment — and answering 4xx would make them retry it for hours.
         */
        return new JsonResponse(['status' => $updated > 0 ? 'recorded' : 'unknown'], 200);
    }

    private function assertKnownChannel(string $channel): void
    {
        if (in_array($channel, [PhoneChannelAdapter::WHATSAPP, PhoneChannelAdapter::SMS], true)) {
            return;
        }

        throw ProblemException::make(
            'platform.not_found',
            'Resource not found',
            404,
            'No resource matches the requested URI.',
        );
    }

    /**
     * The account whose shared secret matches, or a refusal.
     *
     * Matched against every ACTIVE account on the channel, because a business
     * running two numbers has two secrets and either is legitimate. A disabled
     * account's secret does not open the door: switching a channel off has to
     * stop inbound, which is the whole point of the switch.
     */
    private function assertSignature(Request $request, string $channel): object
    {
        $provided = (string) ($request->header('X-Channel-Signature') ?? '');

        if (trim($provided) === '') {
            throw ProblemException::make(
                'channels.unauthorized',
                'Unrecognised caller',
                401,
                'The request carried no signature.',
            );
        }

        foreach (DB::table('channel_accounts')->where('channel', $channel)->where('is_active', true)->get() as $account) {
            $config = json_decode((string) $account->provider_config, true);
            $secret = is_array($config) ? (string) ($config['webhook_secret'] ?? '') : '';

            // Constant time: a plain `===` leaks the secret one byte at a time
            // to anybody willing to measure.
            if ($secret !== '' && hash_equals($secret, $provided)) {
                return $account;
            }
        }

        throw ProblemException::make(
            'channels.unauthorized',
            'Unrecognised caller',
            401,
            'The signature did not match any active account on this channel.',
        );
    }

    private function providerOf(object $account): string
    {
        $config = json_decode((string) $account->provider_config, true);

        return is_array($config) ? (string) ($config['provider'] ?? 'null') : 'null';
    }
}
