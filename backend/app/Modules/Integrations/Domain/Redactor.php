<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain;

/**
 * Takes the secrets out before the row is written.
 *
 * AT THE POINT OF WRITING, and that placement is the whole guarantee. A
 * redaction step somewhere upstream is a step somebody bypasses the day they
 * add a second call site; a rule that says "don't log credentials" is a rule
 * that depends on whoever writes the next logger having read it.
 *
 * Two layers, and both are needed.
 *
 * The CONFIGURED VALUES are the reliable one: this deployment's actual
 * credential strings, removed by exact match wherever they appear — in a
 * header, in a body, in an error message a client library helpfully included
 * the request in.
 *
 * The HEADER DENY-LIST is the net underneath: `Authorization` and its
 * relatives are redacted whatever they contain, because the day somebody
 * configures a credential this class was not told about is the day the first
 * layer has nothing to match.
 */
final class Redactor
{
    public const REDACTED = '[redacted]';

    /**
     * Headers whose VALUE is never logged, whatever it is.
     *
     * Matched case-insensitively and by substring: `X-Api-Key`,
     * `X-Client-Secret` and `Proxy-Authorization` all have to be caught, and
     * an exact list would miss whichever one this ERP happens to use.
     *
     * @var list<string>
     */
    private const NEVER_LOGGED = [
        'authorization', 'auth', 'token', 'secret', 'key', 'password',
        'credential', 'signature', 'cookie', 'session',
    ];

    /**
     * @param  list<string>  $secrets  This deployment's configured values.
     */
    public function __construct(private readonly array $secrets = []) {}

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    public function headers(array $headers): array
    {
        $clean = [];

        foreach ($headers as $name => $value) {
            $clean[$name] = $this->isSensitive((string) $name)
                ? self::REDACTED
                : $this->text((string) $value);
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public function body(array $body): array
    {
        $clean = [];

        foreach ($body as $key => $value) {
            if ($this->isSensitive((string) $key)) {
                $clean[$key] = self::REDACTED;

                continue;
            }

            $clean[$key] = match (true) {
                is_array($value) => $this->body($value),
                is_string($value) => $this->text($value),
                default => $value,
            };
        }

        return $clean;
    }

    /**
     * Removes any configured secret that appears anywhere in a string.
     *
     * Applied to error messages too, which is where a credential most often
     * escapes: a client library that could not connect will happily put the
     * whole request — headers included — into the exception it throws.
     */
    public function text(string $value): string
    {
        foreach ($this->secrets as $secret) {
            if (trim($secret) === '') {
                continue;
            }

            $value = str_replace($secret, self::REDACTED, $value);
        }

        return $value;
    }

    private function isSensitive(string $name): bool
    {
        $lower = mb_strtolower($name);

        foreach (self::NEVER_LOGGED as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }
}
