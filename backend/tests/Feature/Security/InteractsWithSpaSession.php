<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

/**
 * Makes a test request look like it came from the SPA.
 *
 * Sanctum only attaches the session to an API request whose Origin (or Referer)
 * matches `sanctum.stateful`. That gate is the point of cookie mode — it is why
 * an arbitrary third-party page cannot ride a staff member's session — so the
 * tests present the origin rather than switching the gate off. What runs here is
 * the same path a browser takes.
 */
trait InteractsWithSpaSession
{
    protected string $spaOrigin = 'http://localhost:3000';

    protected function setUpSpaOrigin(): void
    {
        $this->withHeader('Origin', $this->spaOrigin);
    }

    /** Simulates a request from somewhere that is not the SPA. */
    protected function fromForeignOrigin(string $origin = 'http://evil.example'): static
    {
        return $this->withHeader('Origin', $origin);
    }
}
