<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Audit;

use App\Modules\Platform\Audit\Domain\AuditRedactor;
use PHPUnit\Framework\TestCase;

final class AuditRedactorTest extends TestCase
{
    private const PATTERNS = [
        '/password/i', '/secret/i', '/token/i',
        '/api[_-]?key/i', '/authorization/i', '/cookie/i', '/credential/i',
    ];

    private function redactor(): AuditRedactor
    {
        return new AuditRedactor(self::PATTERNS, '[REDACTED]');
    }

    public function test_a_top_level_credential_never_survives(): void
    {
        $result = $this->redactor()->redact(['email' => 'a@b.test', 'password' => 'hunter2']);

        $this->assertSame(['email' => 'a@b.test', 'password' => '[REDACTED]'], $result);
    }

    public function test_it_reaches_credentials_buried_three_levels_deep(): void
    {
        $result = $this->redactor()->redact([
            'mailbox' => ['connection' => ['auth' => ['token' => 'abc123', 'host' => 'mail.test']]],
        ]);

        // A redactor that only checks the top level is a redactor that misses
        // every real payload, because real payloads are nested.
        $this->assertSame('[REDACTED]', $result['mailbox']['connection']['auth']['token']);
        $this->assertSame('mail.test', $result['mailbox']['connection']['auth']['host']);
    }

    public function test_it_reaches_inside_lists(): void
    {
        $result = $this->redactor()->redact([
            'accounts' => [
                ['name' => 'first', 'api_key' => 'k1'],
                ['name' => 'second', 'api_key' => 'k2'],
            ],
        ]);

        $this->assertSame('[REDACTED]', $result['accounts'][0]['api_key']);
        $this->assertSame('[REDACTED]', $result['accounts'][1]['api_key']);
        $this->assertSame('second', $result['accounts'][1]['name']);
    }

    public function test_a_credential_object_is_replaced_whole(): void
    {
        $result = $this->redactor()->redact([
            'credentials' => ['username' => 'svc', 'password' => 'p', 'rotation_days' => 30],
        ]);

        // Not redacted key-by-key: publishing the STRUCTURE of a credential is
        // itself a hint worth withholding.
        $this->assertSame('[REDACTED]', $result['credentials']);
    }

    public function test_matching_ignores_case_and_separator(): void
    {
        $result = $this->redactor()->redact([
            'PASSWORD' => 'a', 'ApiKey' => 'b', 'api-key' => 'c', 'Authorization' => 'd',
        ]);

        foreach (['PASSWORD', 'ApiKey', 'api-key', 'Authorization'] as $key) {
            $this->assertSame('[REDACTED]', $result[$key], "[{$key}] was not redacted.");
        }
    }

    public function test_it_matches_a_credential_word_inside_a_longer_key(): void
    {
        // `current_password`, `password_confirmation`, `reset_token` — the real
        // field names a form actually posts.
        $result = $this->redactor()->redact([
            'current_password' => 'a', 'password_confirmation' => 'b', 'reset_token' => 'c',
        ]);

        $this->assertSame(['current_password' => '[REDACTED]', 'password_confirmation' => '[REDACTED]', 'reset_token' => '[REDACTED]'], $result);
    }

    public function test_everything_else_is_preserved_exactly(): void
    {
        $payload = [
            'name' => 'Hana',
            'count' => 0,
            'active' => false,
            'ratio' => 1.5,
            'missing' => null,
            'nested' => ['deep' => ['value' => 'kept']],
        ];

        // A redactor that mangles ordinary data makes the log untrustworthy in
        // the other direction.
        $this->assertSame($payload, $this->redactor()->redact($payload));
    }

    public function test_null_stays_null(): void
    {
        // Distinguishes "no payload" from "an empty payload" all the way to the
        // column.
        $this->assertNull($this->redactor()->redact(null));
    }

    public function test_a_key_that_merely_sounds_similar_is_left_alone(): void
    {
        $result = $this->redactor()->redact(['tokenizer' => 'kept', 'passwordless' => 'kept']);

        // These DO match — documenting the deliberate trade-off rather than
        // pretending it does not exist. Over-redacting a field named
        // "tokenizer" costs one unreadable value; under-redacting one named
        // "reset_token" costs a credential.
        $this->assertSame(['tokenizer' => '[REDACTED]', 'passwordless' => '[REDACTED]'], $result);
    }
}
