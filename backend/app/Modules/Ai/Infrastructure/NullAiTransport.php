<?php

declare(strict_types=1);

namespace App\Modules\Ai\Infrastructure;

use App\Modules\Ai\Contracts\AiTransport;
use App\Modules\Ai\Contracts\SanitisedPrompt;

/**
 * The transport that ships, and the one every test runs against.
 *
 * It answers nothing, which is exactly the behaviour the product has to be
 * correct under: every capability degrades, every surface renders complete
 * without its AI panel, and the regression suite for the other eleven modules
 * passes unchanged. A test suite that needed a live provider would be a test
 * suite that could not run offline and could not be trusted about cost.
 *
 * It also RECORDS what it was asked to send, so the tests that matter most in
 * this module — that a secret and a file's bytes never appear in an outbound
 * payload — have something to look at.
 */
final class NullAiTransport implements AiTransport
{
    /** @var list<string> */
    private array $sent = [];

    public function complete(SanitisedPrompt $prompt, int $timeoutSeconds): ?string
    {
        $this->sent[] = $prompt->text;

        /*
         * Null, always. Not an empty string and not a canned answer: a null
         * adapter that returned something would let a capability appear to
         * work in every test and fail only in production.
         */
        return null;
    }

    public function name(): string
    {
        return 'null';
    }

    /** @return list<string> */
    public function sent(): array
    {
        return $this->sent;
    }

    public function lastSent(): ?string
    {
        return $this->sent === [] ? null : $this->sent[array_key_last($this->sent)];
    }
}
