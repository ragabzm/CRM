<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Contracts;

/**
 * The one way this product talks to an ERP.
 *
 * ONE PORT AND ONE GENERIC ADAPTER. There is no vendor SDK anywhere in this
 * codebase and no vendor package in the manifest — `NoVendorErpClientTest`
 * fails the build on either. That is not purism: a catalogue of named
 * connectors is a catalogue somebody has to maintain against release notes
 * they never see, and the first one that breaks breaks silently.
 *
 * What varies between one deployment's ERP and another's is CONFIGURATION —
 * an endpoint, an authentication header, a field map — and configuration is a
 * thing an administrator can fix at 3am. A vendor adapter is not.
 */
interface ErpTransport
{
    /**
     * Makes one call and reports what happened, whatever happened.
     *
     * Never throws for a remote failure: an unreachable ERP is an ordinary
     * outcome of talking to somebody else's system, and a caller that had to
     * catch it would be a caller that sometimes forgot.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     */
    public function send(
        string $method,
        string $url,
        array $headers,
        array $body,
        int $timeoutSeconds,
    ): ErpResponse;

    /** For the log and the settings screen. Never a credential. */
    public function name(): string;
}
