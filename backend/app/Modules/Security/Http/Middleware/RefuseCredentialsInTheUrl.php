<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Middleware;

use App\Modules\Platform\Exceptions\ProblemException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A credential in a URL is refused, not quietly ignored.
 *
 * The bearer header is the only way to present a token. A query string is not
 * a slightly worse place to put one — it is a different category of mistake:
 * URLs are written to access logs on every hop, kept in browser history,
 * forwarded in referrer headers, and pasted into support tickets by the person
 * debugging the integration.
 *
 * REFUSED RATHER THAN IGNORED. Accepting the request and simply not reading
 * the parameter would leave an integration working by accident — until
 * somebody removed the header it also happened to be sending. Refusing tells
 * whoever wired it up, at the moment they wired it up, that the token they
 * just leaked into a log needs replacing.
 */
final class RefuseCredentialsInTheUrl
{
    /**
     * Names somebody would reach for. Deliberately generous: this list costs
     * nothing to over-match, and the thing it prevents cannot be undone.
     *
     * @var list<string>
     */
    private const NAMES = ['token', 'api_token', 'api_key', 'apikey', 'access_token', 'bearer', 'key', 'secret'];

    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::NAMES as $name) {
            if ($request->query->has($name)) {
                throw ProblemException::make(
                    'security.credential_in_url',
                    'Send the token in the Authorization header',
                    400,
                    'A credential in a URL is written to access logs, browser history and referrer headers. '.
                    'Use `Authorization: Bearer <token>`, and replace the token that was just sent this way.',
                    ['parameter' => $name],
                );
            }
        }

        return $next($request);
    }
}
