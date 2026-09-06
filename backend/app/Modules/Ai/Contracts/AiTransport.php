<?php

declare(strict_types=1);

namespace App\Modules\Ai\Contracts;

use App\Modules\Ai\Contracts\SanitisedPrompt;

/**
 * What an actual provider implements. One method, one shape.
 *
 * Separate from `AiProvider` on purpose, and the separation is the whole
 * safety property: this takes a `SanitisedPrompt`, which only the sanitiser
 * can build, so a transport CANNOT be handed raw ticket text. There is no flag
 * that skips the sanitiser because there is no signature that would accept the
 * result of skipping it.
 *
 * It knows nothing about capabilities, switches, timeouts or degraded return
 * values. Those live in the guard above it, once, so a second provider is a
 * class with one method rather than a second copy of the product's rules.
 */
interface AiTransport
{
    /**
     * @return string|null Null when the provider did not answer. The guard
     *                     turns that into each capability's own degraded value.
     */
    public function complete(SanitisedPrompt $prompt, int $timeoutSeconds): ?string;

    /** For the log and the settings screen. Never a credential. */
    public function name(): string;
}
