<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Security\Domain\Capabilities;
use PHPUnit\Framework\TestCase;

/**
 * One API, and no private door beside it.
 *
 * The claim is not "there is an API" — there always was. It is that there is
 * only ONE, and the way that claim dies is not a decision anybody makes. It is
 * `/api/internal/tickets`, added because the public shape was awkward for one
 * screen, and from then on there are two validations, two capability gates and
 * two command paths — and the one that gets the security fix is whichever the
 * author happened to be looking at.
 */
final class ThereIsOneApiTest extends TestCase
{
    private function routes(): string
    {
        return (string) file_get_contents(SourceScanner::basePath('routes/api.php'));
    }

    public function test_there_is_no_second_api(): void
    {
        $routes = $this->routes();

        foreach (['api/internal', 'api/v2', "prefix('internal')", "prefix('public')", "prefix('external')"] as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $routes,
                "A second API has appeared at [{$needle}].",
            );
        }
    }

    public function test_the_version_is_in_the_path(): void
    {
        $routes = $this->routes();

        /*
         * A version in a header is a version nobody can see in a log, a curl
         * command or an address bar — and a client that forgets it silently
         * gets whichever version happens to be the default.
         */
        foreach (['Accept-Version', 'X-API-Version', 'X-Api-Version'] as $header) {
            $this->assertStringNotContainsString($header, $routes);
        }
    }

    public function test_a_token_ability_is_always_a_capability(): void
    {
        $source = SourceScanner::codeOnly(
            SourceScanner::basePath('app/Modules/Security/Domain/Api/ApiTokens.php'),
        );

        /*
         * Validated against the fixed matrix, by name. An ability list that
         * named endpoints would become a second permission model the moment a
         * route was split in two — and the two would drift silently, because
         * nothing compares them.
         */
        $this->assertStringContainsString('Capabilities::exists', $source);

        // And never more than the account holds. The gate ANDs them anyway;
        // refusing at issue is what makes the mistake visible.
        $this->assertStringContainsString('$owner->can($ability)', $source);
    }

    public function test_the_capability_gate_checks_the_token_as_well_as_the_person(): void
    {
        $source = SourceScanner::codeOnly(
            SourceScanner::basePath('app/Modules/Security/Http/Middleware/RequireCapability.php'),
        );

        /*
         * Both, in that order. A gate that checked only the person would hand
         * every token its owner's whole authority; one that checked only the
         * token would let an ability list grant something no role has.
         */
        $this->assertStringContainsString('currentAccessToken', $source);
        $this->assertStringContainsString('tokenCan', $source);
    }

    public function test_no_capability_is_missing_from_the_ability_vocabulary(): void
    {
        /*
         * Abilities ARE capabilities — there is no separate list to keep in
         * step, which is the point. This asserts the vocabulary is the matrix
         * itself rather than a copy of it.
         */
        $this->assertNotSame([], Capabilities::all());
        $this->assertTrue(Capabilities::exists(Capabilities::TICKET_READ));
        $this->assertFalse(Capabilities::exists('GET /api/v1/tickets'));
    }

    public function test_a_credential_may_not_travel_in_a_url(): void
    {
        $middleware = SourceScanner::codeOnly(
            SourceScanner::basePath('app/Modules/Security/Http/Middleware/RefuseCredentialsInTheUrl.php'),
        );

        // Refused, not ignored: the URL is already in an access log by the
        // time anybody reads the refusal.
        $this->assertStringContainsString('credential_in_url', $middleware);
        $this->assertStringContainsString("'access_token'", $middleware);

        $this->assertStringContainsString(
            'RefuseCredentialsInTheUrl::class',
            $this->routes(),
            'The middleware exists but nothing runs it.',
        );
    }
}
