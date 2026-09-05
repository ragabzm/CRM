<?php

declare(strict_types=1);

namespace App\Modules\Channels\Infrastructure;

use App\Modules\Channels\Contracts\ChannelTransport;
use Illuminate\Support\Str;

/**
 * The transport that sends nothing and remembers everything.
 *
 * The DEFAULT, not a test double bolted on afterwards. A fresh checkout, CI and
 * a laptop with no provider account all work, and every test about retries,
 * delivery states and threading runs without a network — a suite that quietly
 * required a WhatsApp business account would be a suite nobody could run.
 *
 * It records rather than discards, so a test can assert what would have gone
 * out. A null transport that returned silently would make "did we send the
 * right thing?" unanswerable, which is most of what there is to test here.
 */
final class NullChannelTransport implements ChannelTransport
{
    /** @var list<array{to: string, body: string, provider: string}> */
    private array $sent = [];

    public function __construct(private readonly string $provider = 'null') {}

    public function provider(): string
    {
        return $this->provider;
    }

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function send(string $to, string $body, array $configuration): string
    {
        $this->sent[] = ['to' => $to, 'body' => $body, 'provider' => $this->provider];

        // A plausible provider id, so the delivery-receipt path has something
        // to match on in a test.
        return 'null:'.Str::ulid();
    }

    public function supportsInbound(): bool
    {
        return true;
    }

    /** @return list<array{to: string, body: string, provider: string}> */
    public function sent(): array
    {
        return $this->sent;
    }
}
