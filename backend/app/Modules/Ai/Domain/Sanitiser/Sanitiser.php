<?php

declare(strict_types=1);

namespace App\Modules\Ai\Domain\Sanitiser;

use App\Modules\Ai\Contracts\SanitisedPrompt;
use App\Modules\Platform\Support\Settings\SettingsRegistry;

/**
 * The gate every outbound AI request passes through.
 *
 * Three jobs, in this order, and the order matters:
 *
 *   1. REFUSE what must never leave. Credentials, secrets, API keys and
 *      tokens, and the bytes of any attachment. These are not redacted — a
 *      redacted secret is still a secret that was in the buffer — they cause
 *      the request to carry nothing at all.
 *
 *   2. REDACT what identifies a person. Names, addresses, contact details,
 *      references. What cannot be confidently redacted is omitted.
 *
 *   3. CAP the size. A request over the limit sends LESS, never more, and
 *      says that it was shortened rather than quietly handing a model half a
 *      sentence and letting it guess the rest.
 *
 * It lives inside the port and cannot be bypassed: the transport accepts only
 * a `SanitisedPrompt`, and this is the only class that builds one.
 */
final class Sanitiser
{
    public const MAX_CHARACTERS = 'ai.max_characters';

    /**
     * Names of things that are never sent, whatever they contain.
     *
     * Matched against the KEY a caller labelled its content with, so a payload
     * that says "here is the api_key" is refused on the label rather than on
     * whether the value happens to look like one.
     *
     * @var list<string>
     */
    private const NEVER_SENT = [
        'password', 'secret', 'token', 'api_key', 'apikey', 'credential',
        'authorization', 'auth', 'key', 'private', 'signature', 'webhook_secret',
        'attachment', 'attachments', 'file', 'files', 'bytes', 'content',
    ];

    public function __construct(
        private readonly Redactor $redactor,
        private readonly SettingsRegistry $settings,
    ) {}

    /**
     * @param  array<string, string>  $parts  Labelled fragments, in the order
     *                                        they should be sent. The LABEL is
     *                                        checked against the never-sent
     *                                        list before the value is read.
     * @param  list<string>  $declared  Values the caller knows are personal.
     */
    public function prepare(array $parts, array $declared = []): SanitisedPrompt
    {
        $kept = [];

        foreach ($parts as $label => $value) {
            if ($this->neverSent((string) $label)) {
                /*
                 * Dropped entirely, and not replaced with a placeholder. A
                 * line saying "api_key: [redacted]" tells a model there is a
                 * key, which is a fact about this deployment it has no reason
                 * to hold.
                 */
                continue;
            }

            $kept[] = $this->redactor->redact((string) $value, $declared);
        }

        $text = trim(implode("\n", $kept));
        $limit = max(200, (int) $this->settings->get(self::MAX_CHARACTERS));

        if (mb_strlen($text) <= $limit) {
            return new SanitisedPrompt($text);
        }

        return new SanitisedPrompt($this->shorten($text, $limit), shortened: true);
    }

    private function neverSent(string $label): bool
    {
        $lower = mb_strtolower($label);

        foreach (self::NEVER_SENT as $forbidden) {
            if (str_contains($lower, $forbidden)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cuts at a boundary somebody wrote, and says it cut.
     *
     * Never mid-sentence: a model handed "the charge on my card was" will
     * finish the sentence itself, and the answer an agent then reads is about
     * something the customer never said.
     */
    private function shorten(string $text, int $limit): string
    {
        $marker = "\n".Redactor::OMITTED;
        $room = $limit - mb_strlen($marker);

        $head = mb_substr($text, 0, $room);

        /*
         * Back up to the last sentence or line ending. If there is none — one
         * enormous unbroken paragraph — the hard cut stands, and the marker is
         * what stops it reading as the whole message.
         */
        $cut = max(
            (int) mb_strrpos($head, "\n"),
            (int) mb_strrpos($head, '. '),
            (int) mb_strrpos($head, '؟ '),
            (int) mb_strrpos($head, '. '),
        );

        if ($cut > (int) ($room * 0.5)) {
            $head = mb_substr($head, 0, $cut);
        }

        return rtrim($head).$marker;
    }
}
