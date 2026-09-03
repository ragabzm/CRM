<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The API answers a browser in a way a browser will actually accept.
 *
 * This is a session-cookie API: every call from the SPA carries
 * `credentials: "include"`. A browser applies two extra rules to such a
 * request, and BOTH must hold or the response is thrown away before any
 * JavaScript sees it:
 *
 *   1. `Access-Control-Allow-Origin` must name the origin exactly. `*` is
 *      rejected — it is the one value a credentialed request may not receive.
 *   2. `Access-Control-Allow-Credentials: true` must be present.
 *
 * With no `config/cors.php` published, Laravel's defaults give `*` and no
 * credentials header. The API returned 204 and looked healthy in the log; the
 * browser reported `net::ERR_FAILED` and the portal showed "Something went
 * wrong. Try again." for every sign-in attempt.
 *
 * NOTHING IN THE SUITE COULD SEE IT. `$this->get()` is not a browser: it does
 * not send an `Origin`, does not preflight, and does not enforce a single CORS
 * rule. Every test passed against a configuration no browser would accept.
 * These cases send the `Origin` header a browser sends and assert on what a
 * browser actually checks.
 */
final class CorsAllowsTheBrowserToSendCookiesTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function browserEntryPoints(): array
    {
        return [
            /*
             * The SPA's first call, and NOT under `api/*` — the path most
             * likely to be left out of an allow-list, and the one whose
             * absence breaks sign-in before it starts.
             */
            'the csrf cookie' => ['/sanctum/csrf-cookie'],
            'the api' => ['/api/v1/health'],
        ];
    }

    #[DataProvider('browserEntryPoints')]
    public function test_an_allowed_origin_is_named_not_wildcarded(string $path): void
    {
        $origin = (string) Config::get('cors.allowed_origins.0');

        $response = $this->withHeader('Origin', $origin)->get($path);

        $allowed = $response->headers->get('Access-Control-Allow-Origin');

        $this->assertNotSame(
            '*',
            $allowed,
            'A wildcard is refused by every browser on a credentialed request, '
            .'so the SPA never receives this response.',
        );

        $this->assertSame($origin, $allowed);
    }

    #[DataProvider('browserEntryPoints')]
    public function test_the_browser_is_told_it_may_send_the_session_cookie(string $path): void
    {
        $origin = (string) Config::get('cors.allowed_origins.0');

        $this->withHeader('Origin', $origin)
            ->get($path)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_a_preflight_from_the_spa_is_answered(): void
    {
        $origin = (string) Config::get('cors.allowed_origins.0');

        /*
         * Any write from the SPA is preflighted, because it carries
         * `Content-Type: application/json` and an `Idempotency-Key`. A
         * preflight that is not answered means the write never leaves the
         * browser.
         */
        $response = $this->call('OPTIONS', '/api/v1/tickets', [], [], [], [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,idempotency-key,x-xsrf-token',
        ]);

        $this->assertLessThan(300, $response->getStatusCode());
        $response->assertHeader('Access-Control-Allow-Origin', $origin);
        $response->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_a_site_we_do_not_know_is_not_handed_the_session(): void
    {
        /*
         * The other half of the rule. Echoing whatever origin asked would let
         * any page on the internet make authenticated calls with the signed-in
         * person's cookie.
         */
        $response = $this->withHeader('Origin', 'https://not-ours.example')
            ->get('/api/v1/health');

        $this->assertNotSame(
            'https://not-ours.example',
            $response->headers->get('Access-Control-Allow-Origin'),
        );
    }

    public function test_the_headers_the_client_reads_are_exposed(): void
    {
        $origin = (string) Config::get('cors.allowed_origins.0');

        $exposed = (string) $this->withHeader('Origin', $origin)
            ->get('/api/v1/health')
            ->headers->get('Access-Control-Expose-Headers');

        /*
         * Optimistic concurrency depends on the client reading the ETag back.
         * Cross-origin, a header it is not told about does not exist.
         */
        $this->assertStringContainsString('ETag', $exposed);
    }
}
