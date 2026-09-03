<?php

declare(strict_types=1);

namespace App\Modules\Email\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Email\Domain\Inbound\InboundMailIntake;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Where the provider delivers a customer's email.
 *
 * The ONE unauthenticated write in the system, so its guard is doing real work:
 * a shared secret, compared in constant time, that an administrator sets and a
 * provider sends. Without it, anybody who found this URL could open tickets in
 * any customer's name.
 *
 * Deliberately NOT behind the session guard: the caller is a machine with no
 * session, and putting it behind Sanctum would mean either giving a provider an
 * account or exempting the route anyway.
 *
 * Always answers 200 once the message is ours. A provider that receives a 500
 * retries — and retrying a message we have already quarantined would fill
 * quarantine with copies of it. "Received, and here is what happened" is the
 * honest answer to a delivery that arrived intact but could not be understood.
 */
final class InboundWebhookController extends Controller
{
    public function __construct(
        private readonly InboundMailIntake $intake,
        private readonly SettingsRegistry $settings,
    ) {}

    /**
     * @response array{status: string}
     */
    public function store(Request $request): JsonResponse
    {
        $this->assertEnabled();
        $this->assertSignature($request);

        $raw = $this->rawMessage($request);

        $result = $this->intake->accept(
            $raw,
            (string) $this->settings->get('email.inbound.provider'),
            $this->externalId($request),
        );

        return new JsonResponse($result, 200);
    }

    private function assertEnabled(): void
    {
        if ((bool) $this->settings->get('email.inbound.enabled')) {
            return;
        }

        /*
         * 404, not 403. A disabled endpoint should be indistinguishable from
         * one that was never built: telling an unauthenticated caller "this
         * exists but is switched off" is telling them to come back later.
         */
        throw ProblemException::make(
            'platform.not_found',
            'Resource not found',
            404,
            'No resource matches the requested URI.',
        );
    }

    private function assertSignature(Request $request): void
    {
        $expected = (string) $this->settings->get('email.inbound.webhook_secret');

        if (trim($expected) === '') {
            /*
             * Refused rather than waved through. An unset secret means nobody
             * has configured this yet, and accepting anonymous mail into the
             * ticket system in the meantime is worse than not accepting mail.
             */
            throw ProblemException::make(
                'email.inbound_not_configured',
                'Inbound email is not configured',
                503,
                'No webhook secret has been set.',
            );
        }

        $provided = (string) ($request->header('X-Webhook-Secret') ?? $request->query('secret', ''));

        // Constant time: a plain `===` leaks the secret one byte at a time to
        // anybody willing to measure.
        if (! hash_equals($expected, $provided)) {
            throw ProblemException::make(
                'email.inbound_unauthorized',
                'Unrecognised caller',
                401,
                'The webhook secret did not match.',
            );
        }
    }

    /**
     * The raw RFC 5322 bytes.
     *
     * Providers disagree about how to send them — a `raw` field in JSON, a
     * `message` form field, or the whole body — so all three are accepted. The
     * alternative is a provider-specific controller per vendor, which is the
     * webhook subsystem this story is explicitly not building.
     */
    private function rawMessage(Request $request): string
    {
        $named = false;

        foreach (['raw', 'message', 'email', 'body-mime'] as $field) {
            if (! $request->has($field)) {
                continue;
            }

            $named = true;
            $value = $request->input($field);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        /*
         * The whole body is the message ONLY when no named field was sent.
         *
         * A caller that sent `{"raw": ""}` has told us where the message is and
         * that there isn't one. Falling through would quarantine the JSON
         * envelope as if it were an email — a permanent row containing our own
         * request format, filed as a customer's unreadable message.
         */
        $body = $named ? '' : $request->getContent();

        if (trim($body) === '') {
            throw ProblemException::make(
                'email.inbound_empty',
                'Nothing to process',
                422,
                'The request carried no message.',
            );
        }

        return $body;
    }

    private function externalId(Request $request): ?string
    {
        foreach (['message_id', 'MessageID', 'id'] as $field) {
            $value = $request->input($field);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        // Null lets the intake fall back to the MIME Message-ID, then to a
        // hash of the bytes.
        return null;
    }
}
