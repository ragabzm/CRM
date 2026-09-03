<?php

declare(strict_types=1);

namespace App\Modules\Email\Domain\Inbound;

use RuntimeException;
use Throwable;
use ZBateson\MailMimeParser\MailMimeParser;

/**
 * Turns raw RFC 5322 bytes into something the rest of the system can use.
 *
 * A real parser, not a regex. Email is a format with thirty years of
 * accumulated reality in it — folded headers, RFC 2047 encoded words, nested
 * multipart, eight character sets — and every hand-rolled parser eventually
 * meets a message it silently reads wrong. Reading a From address wrong means
 * attaching a customer's words to the wrong person's file.
 *
 * Failure is LOUD. A message this cannot read goes to quarantine with its
 * bytes, never to a best guess: a half-parsed email attached to a plausible
 * ticket is worse than one an administrator can see was not handled.
 */
final class MailParser
{
    /**
     * Headers that mean "a machine sent this".
     *
     * Any one of them is enough. They are the difference between a support desk
     * and two mailboxes replying to each other forever.
     */
    private const AUTOMATION_HEADERS = ['auto-submitted', 'x-auto-response-suppress', 'list-id', 'list-unsubscribe'];

    public function parse(string $raw): ParsedMail
    {
        if (trim($raw) === '') {
            throw new RuntimeException('The message was empty.');
        }

        try {
            $message = (new MailMimeParser)->parse($raw, false);
        } catch (Throwable $e) {
            throw new RuntimeException('The message could not be parsed: '.$e->getMessage(), 0, $e);
        }

        $from = $message->getHeader('From');
        $fromAddress = $from?->getAddresses()[0]?->getEmail();

        if ($fromAddress === null || trim($fromAddress) === '') {
            /*
             * No sender is fatal, not a default. Everything downstream — who
             * the customer is, which ticket this belongs to, who gets replied
             * to — hangs off this one value, and inventing one would attach a
             * stranger's words to somebody's file.
             */
            throw new RuntimeException('The message has no readable From address.');
        }

        $body = $message->getTextContent() ?? $message->getHtmlContent() ?? '';

        return new ParsedMail(
            messageId: $this->firstId($message, 'Message-ID'),
            inReplyTo: $this->firstId($message, 'In-Reply-To'),
            references: $this->ids($message, 'References'),
            fromAddress: strtolower(trim($fromAddress)),
            fromName: trim((string) ($from?->getAddresses()[0]?->getName() ?? '')) ?: $fromAddress,
            subject: (string) ($message->getHeaderValue('Subject') ?? ''),
            body: trim($body),
            attachments: $this->attachments($message),
            isAutomated: $this->looksAutomated($message),
        );
    }

    private function header(object $message, string $name): ?string
    {
        $value = $message->getHeaderValue($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * Every id in an identification header.
     *
     * NOT `getHeaderValue`, which returns only the FIRST id — so a References
     * chain of eight came back as one, and every reply whose parent was not the
     * oldest message in the thread failed to correlate. The header object
     * exposes the whole list; the string accessor quietly does not.
     *
     * @return list<string>
     */
    private function ids(object $message, string $name): array
    {
        $header = $message->getHeader($name);

        if ($header === null || ! method_exists($header, 'getIds')) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (string $id): string => trim($id, '<> '), $header->getIds()),
            static fn (string $id): bool => $id !== '',
        ));
    }

    /** The single id an identification header carries, if any. */
    private function firstId(object $message, string $name): ?string
    {
        return $this->ids($message, $name)[0] ?? null;
    }

    /** @return list<array{filename: string, mime: string, bytes: string}> */
    private function attachments(object $message): array
    {
        $found = [];

        foreach ($message->getAllAttachmentParts() as $part) {
            $content = $part->getContent();

            if ($content === null || $content === '') {
                continue;
            }

            $found[] = [
                'filename' => (string) ($part->getFilename() ?? 'attachment'),
                /*
                 * The sender's claim about the type, recorded but NOT trusted.
                 * The attachment subsystem sniffs the bytes itself; this is
                 * only here so a filename with no extension still has
                 * something to show.
                 */
                'mime' => (string) ($part->getContentType() ?? 'application/octet-stream'),
                'bytes' => $content,
            ];
        }

        return $found;
    }

    private function looksAutomated(object $message): bool
    {
        foreach (self::AUTOMATION_HEADERS as $name) {
            $value = $message->getHeaderValue($name);

            if (is_string($value) && trim($value) !== '') {
                // `auto-submitted: no` is the one value that means "a person
                // sent this" — everything else is a machine saying so.
                if ($name === 'auto-submitted' && strtolower(trim($value)) === 'no') {
                    continue;
                }

                return true;
            }
        }

        $precedence = $message->getHeaderValue('Precedence');

        return is_string($precedence)
            && in_array(strtolower(trim($precedence)), ['bulk', 'junk', 'list'], true);
    }
}
