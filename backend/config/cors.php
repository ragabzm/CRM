<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing
|--------------------------------------------------------------------------
|
| The frontend and the API are separate origins — `localhost:3000` talks to
| `localhost:8000` in development, and two hostnames in production. Every
| browser-side call carries `credentials: "include"`, because this is a
| session-cookie API, not a bearer-token one.
|
| Laravel ships `HandleCors` in the global stack and, with no config file
| published, falls back to `allowed_origins => ['*']` and
| `supports_credentials => false`. A browser refuses BOTH of those on a
| credentialed request: the wildcard is rejected outright, and without
| `Access-Control-Allow-Credentials: true` the response is discarded before
| any JavaScript sees it.
|
| So the API answered every preflight with a header that looked permissive
| and was, in practice, a closed door. The failure shows in the browser as
| `net::ERR_FAILED` on `/sanctum/csrf-cookie` and, in the interface, as
| "Something went wrong. Try again." — with a 204 in the server log, which is
| why nothing looked wrong from the API side.
|
*/

return [

    /*
     * `sanctum/csrf-cookie` is NOT under `api/*` and is the first call the SPA
     * makes. Leaving it out means sign-in fails before it starts.
     */
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    /*
     * Named origins, never a wildcard. With credentials on, the browser
     * requires an exact echo — and a wildcard on a cookie API would let any
     * site on the internet make authenticated calls with the user's session.
     */
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    /*
     * What the SPA reads off a response. Everything else stays invisible to
     * JavaScript no matter what the server sends.
     */
    'exposed_headers' => [
        // Optimistic concurrency: the client echoes this back on the next write.
        'ETag',
        // Told to the person when a write is rejected for coming too fast.
        'Retry-After',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
    ],

    'max_age' => 3600,

    /*
     * The whole point. Without this the session cookie is never attached and
     * every request arrives unauthenticated.
     */
    'supports_credentials' => true,

];
