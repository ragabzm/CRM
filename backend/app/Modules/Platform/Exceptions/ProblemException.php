<?php

declare(strict_types=1);

namespace App\Modules\Platform\Exceptions;

use App\Modules\Platform\Http\ProblemDetails;
use RuntimeException;
use Throwable;

/**
 * Thrown when code needs to choose the problem code itself rather than let the
 * handler infer one from a framework exception.
 *
 * This is the *only* sanctioned way for non-Platform code to influence an error
 * response body — controllers never write one directly (enforced by
 * tests/Architecture/NoControllerErrorBodiesTest.php).
 */
final class ProblemException extends RuntimeException
{
    public function __construct(
        public readonly ProblemDetails $problem,
        ?Throwable $previous = null,
    ) {
        parent::__construct($problem->detail ?? $problem->title, $problem->status, $previous);
    }

    /**
     * @param  array<string, mixed>  $extensions
     */
    public static function make(string $code, string $title, int $status, ?string $detail = null, array $extensions = []): self
    {
        return new self(new ProblemDetails($code, $title, $status, $detail, $extensions));
    }
}
