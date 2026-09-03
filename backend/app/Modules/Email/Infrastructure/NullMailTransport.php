<?php

declare(strict_types=1);

namespace App\Modules\Email\Infrastructure;

use App\Modules\Email\Contracts\MailTransport;

/**
 * The transport that delivers nothing and remembers everything.
 *
 * THE DEFAULT, including in CI. A test suite that quietly required a reachable
 * SMTP server would be a suite nobody could run on a laptop or in a fresh
 * container — and the first thing anyone would do is skip the email tests.
 *
 * It records each send so a test can assert what WOULD have gone out: the
 * headers, the threading, the language. Those are the things that actually
 * break, and none of them need a network to check.
 */
final class NullMailTransport implements MailTransport
{
    /** @var list<array<string, mixed>> */
    private array $sent = [];

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
        $this->sent[] = [
            'to_address' => $toAddress,
            'to_name' => $toName,
            'subject' => $subject,
            'body' => $body,
            'headers' => $headers,
            'locale' => $locale,
        ];
    }

    public function name(): string
    {
        return 'null';
    }

    /** @return list<array<string, mixed>> */
    public function sent(): array
    {
        return $this->sent;
    }

    /** @return array<string, mixed>|null */
    public function lastSent(): ?array
    {
        return $this->sent === [] ? null : $this->sent[array_key_last($this->sent)];
    }

    public function forget(): void
    {
        $this->sent = [];
    }
}
