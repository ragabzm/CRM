<?php

declare(strict_types=1);

namespace App\Modules\Email\Infrastructure;

use App\Modules\Email\Contracts\MailTransport;
use App\Modules\Email\Contracts\MailTransportFailure;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email as MimeEmail;
use Throwable;

/**
 * The real one: Laravel's mail layer, whichever driver it is configured with.
 *
 * SMTP, Postmark, Mailgun and SES are all Laravel mailers, so a single adapter
 * covers every provider the story names. Choosing between them is a setting
 * that selects a mailer, not a class anybody has to write.
 *
 * Built as a raw Symfony message rather than through a Mailable: the headers
 * this story exists to get right — Message-ID, In-Reply-To, References — are
 * set on the MIME message, and a Mailable would put a rendering layer between
 * this code and the bytes that matter.
 */
final class LaravelMailTransport implements MailTransport
{
    /**
     * The headers Symfony insists are identification headers.
     *
     * Passing any of these as a text header throws before the message leaves.
     */
    private const IDENTIFICATION_HEADERS = ['Message-ID', 'In-Reply-To', 'References'];

    public function __construct(
        private readonly MailFactory $mailer,
        private readonly string $mailerName,
        private readonly string $fromAddress,
        private readonly string $fromName,
    ) {}

    /**
     * @param  array<string, string>  $headers
     */
    public function send(
        string $toAddress,
        string $toName,
        string $subject,
        string $body,
        array $headers,
        string $locale,
    ): void {
        try {
            $this->mailer->mailer($this->mailerName)->html($body, function (mixed $message) use (
                $toAddress, $toName, $subject, $headers, $locale
            ): void {
                $message->from($this->fromAddress, $this->fromName)
                    ->to($toAddress, $toName)
                    ->subject($subject);

                $mime = $message->getSymfonyMessage();

                if (! $mime instanceof MimeEmail) {
                    return;
                }

                /*
                 * The whole point of this story's threading work. Without these
                 * the customer's mail client files the reply as a new
                 * conversation, and the thread they can see falls apart even
                 * though ours stays intact.
                 */
                foreach ($headers as $name => $value) {
                    if ($value === '') {
                        continue;
                    }

                    if (in_array($name, self::IDENTIFICATION_HEADERS, true)) {
                        /*
                         * `addIdHeader`, not `addTextHeader`.
                         *
                         * Symfony types these three as identification headers
                         * and REFUSES a text header under those names — the
                         * send throws before it reaches the provider. Caught by
                         * running against a real mailer: every test passed,
                         * because the null transport never touches Symfony's
                         * MIME layer and so exercised the header VALUES without
                         * ever assembling a message.
                         *
                         * It also wants bare ids and writes the angle brackets
                         * itself; passing `<a@b>` would produce `<<a@b>>`.
                         */
                        $mime->getHeaders()->addIdHeader($name, self::bareIds($value));

                        continue;
                    }

                    $mime->getHeaders()->addTextHeader($name, $value);
                }

                // So a client that honours it renders in the right direction
                // without guessing from the body.
                $mime->getHeaders()->addTextHeader('Content-Language', $locale);
            });
        } catch (TransportExceptionInterface $e) {
            /*
             * Symfony's transport exceptions are connection- and
             * protocol-level: refused connections, TLS failures, timeouts, 4xx
             * SMTP replies. Every one of those can succeed on a second attempt.
             */
            throw MailTransportFailure::temporary($e->getMessage(), previous: $e);
        } catch (Throwable $e) {
            /*
             * Anything else got as far as being rejected on its content — a
             * malformed address, a sender the provider will not accept.
             * Retrying that burns the provider's reputation on a message that
             * can never land, and delays the moment anyone finds out.
             */
            throw MailTransportFailure::permanent($e->getMessage(), previous: $e);
        }
    }

    public function name(): string
    {
        return $this->mailerName;
    }

    /**
     * Strips the angle brackets Symfony adds back itself.
     *
     * A References header carries several ids separated by spaces, so this
     * returns a list rather than a string.
     *
     * @return list<string>|string
     */
    private static function bareIds(string $value): array|string
    {
        $ids = array_values(array_filter(
            array_map(
                static fn (string $id): string => trim($id, '<> '),
                preg_split('/\s+/', trim($value)) ?: [],
            ),
            static fn (string $id): bool => $id !== '',
        ));

        return count($ids) === 1 ? $ids[0] : $ids;
    }
}
