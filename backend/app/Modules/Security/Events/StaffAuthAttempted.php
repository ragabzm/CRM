<?php

declare(strict_types=1);

namespace App\Modules\Security\Events;

/**
 * A staff sign-in was attempted, successfully or not.
 *
 * Both outcomes are events on purpose. A failure that is not recorded is a brute
 * force nobody can see, and a success that is not recorded means an incident
 * review cannot answer "who was in the system, and when".
 *
 * Carries NO credential of any kind — not the password, not a hash of it. There
 * is no downstream consumer that would need one, and a value that is never in
 * the object cannot be leaked by a listener that logs the whole event.
 */
final class StaffAuthAttempted
{
    public const OUTCOME_SUCCESS = 'success';

    public const OUTCOME_FAILURE = 'failure';

    public function __construct(
        public readonly string $email,
        public readonly string $outcome,
        public readonly ?string $ip = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $userId = null,
    ) {}

    public static function success(string $email, ?string $ip, ?string $userAgent, ?string $userId): self
    {
        return new self($email, self::OUTCOME_SUCCESS, $ip, $userAgent, $userId);
    }

    public static function failure(string $email, ?string $ip, ?string $userAgent): self
    {
        return new self($email, self::OUTCOME_FAILURE, $ip, $userAgent);
    }
}
