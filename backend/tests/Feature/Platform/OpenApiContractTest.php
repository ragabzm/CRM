<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Platform\Http\Middleware\IdempotencyKey;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * The committed openapi.yaml is the contract the frontend client is generated
 * from, so staleness here is a silent, cross-repository bug.
 */
final class OpenApiContractTest extends TestCase
{
    public function test_the_committed_document_is_not_stale(): void
    {
        $this->artisan('openapi:check')->assertSuccessful();
    }

    public function test_it_is_an_openapi_31_document(): void
    {
        $this->assertSame('3.1.0', $this->spec()['openapi']);
    }

    public function test_it_documents_the_health_endpoint(): void
    {
        $this->assertArrayHasKey('/healthz', $this->spec()['paths']);
    }

    public function test_every_operation_declares_the_problem_error_shape(): void
    {
        foreach ($this->spec()['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                $this->assertSame(
                    '#/components/schemas/Problem',
                    $operation['responses']['default']['content']['application/problem+json']['schema']['$ref'] ?? null,
                    strtoupper((string) $method)." {$path} does not declare the problem+json error shape."
                );
            }
        }
    }

    public function test_write_operations_require_an_idempotency_key(): void
    {
        $writes = 0;

        foreach ($this->spec()['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (! in_array(strtolower((string) $method), ['post', 'put', 'patch', 'delete'], true)) {
                    continue;
                }

                $writes++;
                $names = array_column($operation['parameters'] ?? [], 'name');

                $this->assertContains(
                    IdempotencyKey::HEADER,
                    $names,
                    strtoupper((string) $method)." {$path} must document the ".IdempotencyKey::HEADER.' header.'
                );
            }
        }

        $this->assertGreaterThan(0, $writes, 'The document should contain at least one write operation.');
    }

    /**
     * @return array<string, mixed>
     */
    private function spec(): array
    {
        return Yaml::parseFile(base_path('openapi.yaml'));
    }
}
