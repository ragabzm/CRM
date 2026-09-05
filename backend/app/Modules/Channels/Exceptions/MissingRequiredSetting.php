<?php

declare(strict_types=1);

namespace App\Modules\Channels\Exceptions;

use RuntimeException;

/**
 * A setting the intake cannot run without has no value.
 *
 * There is exactly one: the default department. Every other setting has a
 * defensible default; a department does not, because guessing one puts a
 * customer's ticket in front of a team that does not handle it and no error is
 * ever raised.
 */
final class MissingRequiredSetting extends RuntimeException
{
    public static function named(string $key): self
    {
        return new self(
            "The setting '{$key}' has no value. Inbound messages cannot be routed until it is set."
        );
    }
}
