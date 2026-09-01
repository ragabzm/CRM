<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Support\RequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * First middleware in the api group. Establishes the correlation id and the
 * actor/module facts that every log line and every problem document carry.
 *
 * An inbound X-Request-Id is honoured so a trace survives the hop from the
 * Next.js frontend into the API; anything malformed is replaced rather than
 * trusted, since the value ends up in logs.
 */
final class AssignRequestId
{
    public const HEADER = 'X-Request-Id';

    /** Guards against log injection and unbounded header values. */
    private const INBOUND_PATTERN = '/^[A-Za-z0-9_-]{8,64}$/';

    public function __construct(
        private readonly RequestContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);

        $this->context->setRequestId($requestId);
        $this->context->setModule($this->resolveModule($request));
        $request->headers->set(self::HEADER, $requestId);

        /** @var Response $response */
        $response = $next($request);

        // Set after the fact so a replayed idempotent response still reports the
        // id of the request the caller actually made.
        $response->headers->set(self::HEADER, $requestId);

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $inbound = $request->headers->get(self::HEADER);

        if (is_string($inbound) && preg_match(self::INBOUND_PATTERN, $inbound) === 1) {
            return $inbound;
        }

        return (string) Str::ulid();
    }

    /**
     * Derive the owning module from the controller namespace, e.g.
     * App\Modules\Tickets\Http\Controllers\... -> "Tickets".
     *
     * Closure routes and anything outside app/Modules resolve to null rather
     * than to a guess.
     */
    private function resolveModule(Request $request): ?string
    {
        $action = $request->route()?->getActionName();

        if (! is_string($action)) {
            return null;
        }

        return preg_match('/^App\\\\Modules\\\\([A-Za-z0-9_]+)\\\\/', $action, $m) === 1
            ? $m[1]
            : null;
    }
}
