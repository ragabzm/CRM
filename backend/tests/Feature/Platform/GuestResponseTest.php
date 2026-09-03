<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Tests\TestCase;

/**
 * An unauthenticated caller gets an answer, not a redirect.
 *
 * This backend has no login PAGE — the sign-in screen lives in the separate
 * frontend deployment — so there is nothing to redirect a guest to. Laravel's
 * default is to look up `route('login')` whenever the request does not ask for
 * JSON, which used to throw from inside the auth middleware and surface as a
 * 500.
 */
final class GuestResponseTest extends TestCase
{
    public function test_a_guest_without_an_accept_header_gets_401_not_500(): void
    {
        // No Accept header at all: a curl default, a health prober, a browser
        // address bar. Every one of these used to get a 500.
        $response = $this->call('GET', '/api/v1/customers');

        $response->assertStatus(401);
        $response->assertJsonPath('code', 'platform.unauthorized');
    }

    public function test_a_guest_asking_for_html_still_gets_problem_json(): void
    {
        $response = $this->call('GET', '/api/v1/customers', server: [
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
        ]);

        $response->assertStatus(401);
        $response->assertHeader('Content-Type', 'application/problem+json');
    }

    public function test_a_guest_asking_for_json_is_unchanged(): void
    {
        $this->getJson('/api/v1/customers')
            ->assertStatus(401)
            ->assertJsonPath('code', 'platform.unauthorized');
    }

    public function test_the_response_never_names_an_internal_route(): void
    {
        $response = $this->call('GET', '/api/v1/customers');

        // The old 500 leaked `Route [login] not defined.` into `detail`.
        $this->assertStringNotContainsString('Route [login]', (string) $response->getContent());
    }
}
