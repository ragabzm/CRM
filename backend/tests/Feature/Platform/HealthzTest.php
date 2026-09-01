<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Platform\Http\Middleware\AssignRequestId;
use Tests\TestCase;

final class HealthzTest extends TestCase
{
    public function test_it_reports_ok(): void
    {
        $response = $this->getJson('/api/v1/healthz');

        $response->assertOk();
        $response->assertExactJson(['status' => 'ok']);
    }

    public function test_success_responses_are_plain_json_not_problem_json(): void
    {
        $response = $this->getJson('/api/v1/healthz');

        $this->assertStringStartsWith('application/json', (string) $response->headers->get('Content-Type'));
    }

    public function test_it_returns_a_correlation_id(): void
    {
        $response = $this->getJson('/api/v1/healthz');

        $requestId = $response->headers->get(AssignRequestId::HEADER);

        $this->assertNotNull($requestId, AssignRequestId::HEADER.' header is missing.');
        $this->assertMatchesRegularExpression('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/', $requestId);
    }

    public function test_it_echoes_a_well_formed_inbound_correlation_id(): void
    {
        $response = $this->getJson('/api/v1/healthz', [AssignRequestId::HEADER => 'trace-from-frontend-1']);

        $this->assertSame('trace-from-frontend-1', $response->headers->get(AssignRequestId::HEADER));
    }

    public function test_it_replaces_a_malformed_inbound_correlation_id(): void
    {
        $response = $this->getJson('/api/v1/healthz', [AssignRequestId::HEADER => "bad value\nwith newline"]);

        $this->assertNotSame("bad value\nwith newline", $response->headers->get(AssignRequestId::HEADER));
    }
}
