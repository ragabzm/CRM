<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Platform\Http\Middleware\AssignRequestId;
use App\Modules\Platform\Http\ProblemDetails;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

/**
 * One case per error family. Together these are the proof that the shared
 * handler — and only the shared handler — shapes every error body.
 */
final class ProblemDetailsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->prefix('api/v1/__problem')->group(function (): void {
            Route::get('/validation', fn () => throw ValidationException::withMessages([
                'email' => ['The email field is required.'],
            ]));
            Route::get('/forbidden', fn () => throw new AuthorizationException);
            Route::get('/throttled', fn () => throw new TooManyRequestsHttpException(60));
            Route::get('/boom', fn () => throw new RuntimeException('internal detail that must not leak'));
            Route::get('/guarded', fn () => 'never reached')->middleware('auth:sanctum');
        });
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int, 3: string}>
     */
    public static function errorFamilies(): array
    {
        return [
            'not found' => ['get', '/api/v1/does-not-exist', 404, 'platform.not_found'],
            'method not allowed' => ['post', '/api/v1/healthz', 405, 'platform.method_not_allowed'],
            'validation failed' => ['get', '/api/v1/__problem/validation', 422, 'platform.validation_failed'],
            'unauthorized' => ['get', '/api/v1/__problem/guarded', 401, 'platform.unauthorized'],
            'forbidden' => ['get', '/api/v1/__problem/forbidden', 403, 'platform.forbidden'],
            'too many requests' => ['get', '/api/v1/__problem/throttled', 429, 'platform.too_many_requests'],
            'internal error' => ['get', '/api/v1/__problem/boom', 500, 'platform.internal_error'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('errorFamilies')]
    public function test_every_error_family_is_a_problem_document(string $method, string $uri, int $status, string $code): void
    {
        config()->set('app.debug', false);

        $response = $this->{$method.'Json'}($uri);

        $response->assertStatus($status);

        $this->assertStringStartsWith(
            ProblemDetails::CONTENT_TYPE,
            (string) $response->headers->get('Content-Type'),
            "{$uri} did not return a problem+json content type."
        );

        $body = $response->json();

        $this->assertSame($code, $body['code']);
        $this->assertSame($status, $body['status']);
        $this->assertSame('https://errors.ragab-crm/'.$code, $body['type']);
        $this->assertNotEmpty($body['title']);
        $this->assertNotEmpty($body['trace_id']);
        $this->assertArrayHasKey('instance', $body);

        $this->assertMatchesRegularExpression(
            ProblemDetails::CODE_PATTERN,
            $body['code'],
            'Problem codes must be shaped module.condition.'
        );
    }

    public function test_the_trace_id_matches_the_response_correlation_header(): void
    {
        $response = $this->getJson('/api/v1/does-not-exist');

        $this->assertSame(
            $response->headers->get(AssignRequestId::HEADER),
            $response->json('trace_id'),
            'trace_id must be the same correlation id the caller sees in the header.'
        );
    }

    public function test_validation_failures_carry_the_field_errors(): void
    {
        $response = $this->getJson('/api/v1/__problem/validation');

        $response->assertStatus(422);
        $this->assertSame(['The email field is required.'], $response->json('errors.email'));
    }

    public function test_internal_errors_do_not_leak_the_exception_message(): void
    {
        config()->set('app.debug', false);

        $response = $this->getJson('/api/v1/__problem/boom');

        $response->assertStatus(500);
        $this->assertStringNotContainsString('internal detail that must not leak', (string) $response->getContent());
    }
}
