<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Platform\Support\RequestContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;

/**
 * The single place in the system that turns a throwable into an error body.
 *
 * Registered in bootstrap/app.php. Nothing else — no controller, no middleware,
 * no module — is permitted to write a 4xx/5xx body; the architecture tests fail
 * the build if one tries.
 */
final class ProblemDetailsHandler
{
    /**
     * Module-supplied mappers, tried before the built-in table.
     *
     * The extension point exists so a higher tier can name its own conditions
     * without this class — which is T0 — importing that module's vocabulary.
     * Security uses it to narrow a generic 401 into "your session ended".
     *
     * @var list<callable(Throwable, Request): ?ProblemDetails>
     */
    private array $mappers = [];

    public function __construct(
        private readonly RequestContext $context,
    ) {}

    /**
     * @param  callable(Throwable, Request): ?ProblemDetails  $mapper
     */
    public function extend(callable $mapper): void
    {
        // Later registrations win, so a module can refine a mapping another
        // module already made.
        array_unshift($this->mappers, $mapper);
    }

    /**
     * Returns null for requests that should keep Laravel's HTML error pages
     * (the non-API web surface); the caller treats null as "not handled".
     */
    public function __invoke(Throwable $e, Request $request): ?JsonResponse
    {
        if (! $this->shouldHandle($request)) {
            return null;
        }

        return $this->toResponse($this->problemFor($e, $request), $request);
    }

    public function toResponse(ProblemDetails $problem, Request $request): JsonResponse
    {
        $baseUri = (string) config('problem-details.type_base_uri');
        $traceId = $this->context->requestId() ?? (string) Str::ulid();

        $response = new JsonResponse(
            $problem->toArray($baseUri, $request->getRequestUri(), $traceId),
            $problem->status,
            [],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        // RFC 9457 §3: the media type is what makes this a problem document.
        $response->headers->set('Content-Type', ProblemDetails::CONTENT_TYPE);

        // Error responses are rendered outside the middleware stack, so
        // AssignRequestId never gets to stamp them. Do it here instead, using
        // the same id that went into trace_id.
        $response->headers->set(Middleware\AssignRequestId::HEADER, $traceId);

        return $response;
    }

    private function shouldHandle(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    /**
     * Map a throwable onto a stable `module.condition` code.
     *
     * Ordering matters: the most specific types are matched first, and the
     * generic HttpExceptionInterface arm catches everything Symfony throws that
     * we have not named explicitly.
     */
    private function problemFor(Throwable $e, Request $request): ProblemDetails
    {
        foreach ($this->mappers as $mapper) {
            $problem = $mapper($e, $request);

            if ($problem instanceof ProblemDetails) {
                return $problem;
            }
        }

        return match (true) {
            $e instanceof ProblemException => $e->problem,

            $e instanceof ValidationException => new ProblemDetails(
                'platform.validation_failed',
                'The request payload failed validation.',
                422,
                'One or more fields are invalid. See the errors member for details.',
                ['errors' => $e->errors()],
            ),

            /*
             * Stays generic on purpose. Platform is T0 and must not know a
             * higher tier's vocabulary — the Security module narrows this to
             * `security.session_expired` on its own authenticated routes, via
             * SessionExpiryProblem.
             */
            $e instanceof AuthenticationException => new ProblemDetails(
                'platform.unauthorized',
                'Authentication required.',
                401,
                'This endpoint requires an authenticated caller.',
            ),

            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => new ProblemDetails(
                'platform.forbidden',
                'Forbidden.',
                403,
                'The authenticated caller is not allowed to perform this action.',
            ),

            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => new ProblemDetails(
                'platform.not_found',
                'Resource not found.',
                404,
                'No resource matches the requested URI.',
            ),

            $e instanceof MethodNotAllowedHttpException => new ProblemDetails(
                'platform.method_not_allowed',
                'Method not allowed.',
                405,
                'The HTTP method is not supported by this resource.',
            ),

            $e instanceof ConflictHttpException => new ProblemDetails(
                'platform.conflict',
                'Conflict.',
                409,
                'The request conflicts with the current state of the resource.',
            ),

            $e instanceof TooManyRequestsHttpException => new ProblemDetails(
                'platform.too_many_requests',
                'Too many requests.',
                429,
                'The caller has exceeded the rate limit for this endpoint.',
            ),

            $e instanceof UnauthorizedHttpException => new ProblemDetails(
                'platform.unauthorized',
                'Authentication required.',
                401,
                'This endpoint requires an authenticated caller.',
            ),

            $e instanceof HttpExceptionInterface => $this->fromStatus($e),

            default => new ProblemDetails(
                'platform.internal_error',
                'Internal server error.',
                500,
                // Never leak an exception message to a production client; the
                // trace_id is how an operator correlates this with the logs.
                config('app.debug') ? $e->getMessage() : 'An unexpected error occurred.',
            ),
        };
    }

    private function fromStatus(HttpExceptionInterface $e): ProblemDetails
    {
        $status = $e->getStatusCode();

        [$code, $title] = match ($status) {
            400 => ['platform.validation_failed', 'Bad request.'],
            401 => ['platform.unauthorized', 'Authentication required.'],
            403 => ['platform.forbidden', 'Forbidden.'],
            404 => ['platform.not_found', 'Resource not found.'],
            405 => ['platform.method_not_allowed', 'Method not allowed.'],
            409 => ['platform.conflict', 'Conflict.'],
            429 => ['platform.too_many_requests', 'Too many requests.'],
            default => $status >= 500
                ? ['platform.internal_error', 'Internal server error.']
                : ['platform.request_failed', 'Request could not be processed.'],
        };

        $message = $e->getMessage();

        return new ProblemDetails($code, $title, $status, $message !== '' ? $message : null);
    }
}
