<?php

declare(strict_types=1);

namespace App\Modules\Email\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Email\Contracts\MailTransportFailure;
use App\Modules\Email\Domain\OutboundMailer;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Does this configuration actually work?"
 *
 * The only honest way to answer that is to send something. Everything else —
 * a green tick because the fields are filled in, a connection check that does
 * not authenticate — tells an administrator the channel is fine right up until
 * the first customer does not get a reply.
 *
 * Sent SYNCHRONOUSLY, unlike every other outbound email. The whole value is the
 * immediate answer: an administrator changing a credential wants to know now,
 * not to go and read a log. It is also the one send where a person is waiting
 * and a timeout is information rather than an outage.
 */
final class EmailTestSendController extends Controller
{
    public function __construct(
        private readonly OutboundMailer $mailer,
        private readonly SettingsRegistry $settings,
    ) {}

    /**
     * @response array{status: string, provider: string, sent_to: string}
     */
    public function store(Request $request): JsonResponse
    {
        $to = $this->recipient($request);

        try {
            $this->mailer->send(
                toAddress: $to,
                toName: 'Test recipient',
                subject: 'Test message from '.config('app.name'),
                body: 'If you are reading this, outbound email is working.',
                headers: [],
                locale: 'en',
            );
        } catch (MailTransportFailure $failure) {
            /*
             * The provider's own words, verbatim.
             *
             * "Sending failed" is useless: the administrator cannot tell an
             * expired API key from a blocked port from a rejected sender
             * domain. The provider already diagnosed it — passing that through
             * is the difference between a five-minute fix and a support ticket.
             */
            throw ProblemException::make(
                'email.test_send_failed',
                'The test email could not be sent',
                502,
                $failure->getMessage(),
                [
                    'provider' => (string) $this->settings->get('email.provider'),
                    // Whether trying the same thing again could help.
                    'retryable' => $failure->retryable,
                    'provider_code' => $failure->providerCode,
                ],
            );
        }

        return new JsonResponse([
            'status' => 'sent',
            'provider' => (string) $this->settings->get('email.provider'),
            'sent_to' => $to,
        ]);
    }

    /**
     * Where the test goes.
     *
     * The signed-in administrator's own address by default: a test that emails
     * somebody else is a test whose result they cannot see. An explicit address
     * is accepted because verifying delivery to a customer domain is a real
     * thing administrators need to do.
     */
    private function recipient(Request $request): string
    {
        $requested = trim((string) $request->input('to', ''));

        if ($requested !== '') {
            if (filter_var($requested, FILTER_VALIDATE_EMAIL) === false) {
                throw ProblemException::make(
                    'email.invalid_recipient',
                    'That is not an email address',
                    422,
                    'Give a valid address, or leave it blank to send to yourself.',
                );
            }

            return $requested;
        }

        $own = $request->user()?->getAttribute('email');

        if (! is_string($own) || $own === '') {
            throw ProblemException::make(
                'email.no_test_recipient',
                'Nowhere to send the test',
                422,
                'Your account has no email address. Give one explicitly.',
            );
        }

        return $own;
    }
}
