<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Platform\Logging\JsonContextProcessor;
use App\Modules\Platform\Logging\JsonLogFormatter;
use App\Modules\Platform\Support\RequestContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use Tests\TestCase;

final class LoggingTest extends TestCase
{
    private const REQUIRED_KEYS = ['request_id', 'actor_type', 'actor_id', 'module', 'ticket_id'];

    public function test_a_log_line_is_one_json_object_carrying_all_five_fields(): void
    {
        $decoded = $this->captureLine(function (Logger $logger): void {
            $logger->info('ticket assigned');
        });

        $this->assertSame('ticket assigned', $decoded['message']);

        foreach (self::REQUIRED_KEYS as $key) {
            $this->assertArrayHasKey($key, $decoded, "Log line is missing the {$key} field.");
        }
    }

    public function test_unattributed_lines_still_carry_every_key(): void
    {
        $decoded = $this->captureLine(fn (Logger $logger) => $logger->warning('booting'));

        $this->assertNull($decoded['request_id']);
        $this->assertSame('service', $decoded['actor_type']);
        $this->assertNull($decoded['actor_id']);
        $this->assertNull($decoded['module']);
        $this->assertNull($decoded['ticket_id']);
    }

    public function test_the_ambient_request_context_is_projected_onto_the_line(): void
    {
        $context = $this->app->make(RequestContext::class);
        $context->setRequestId('01HZY000000000000000000000');
        $context->setModule('Tickets');
        $context->setActor('user', '42');

        $decoded = $this->captureLine(fn (Logger $logger) => $logger->info('assigned'));

        $this->assertSame('01HZY000000000000000000000', $decoded['request_id']);
        $this->assertSame('Tickets', $decoded['module']);
        $this->assertSame('user', $decoded['actor_type']);
        $this->assertSame('42', $decoded['actor_id']);
    }

    public function test_a_per_call_ticket_id_wins_over_the_ambient_one(): void
    {
        $this->app->make(RequestContext::class)->setTicketId('ambient');

        $decoded = $this->captureLine(
            fn (Logger $logger) => $logger->info('escalated', ['ticket_id' => 'TCK-9', 'reason' => 'sla breach'])
        );

        $this->assertSame('TCK-9', $decoded['ticket_id']);
        // The hoisted key is not duplicated back into the context object.
        $this->assertSame(['reason' => 'sla breach'], $decoded['context']);
    }

    /**
     * Exercises the channel as it is actually configured, not just the classes:
     * a regression in config/logging.php would slip past a unit test.
     */
    public function test_the_configured_json_channel_emits_the_same_shape(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'jsonlog');
        config()->set('logging.channels.json.handler_with', ['stream' => $path]);

        Log::channel('json')->info('via the configured channel');

        $lines = array_values(array_filter(explode("\n", (string) file_get_contents($path))));
        unlink($path);

        $this->assertCount(1, $lines);

        $decoded = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('via the configured channel', $decoded['message']);
        foreach (self::REQUIRED_KEYS as $key) {
            $this->assertArrayHasKey($key, $decoded);
        }
    }

    public function test_an_unauthenticated_api_request_logs_a_guest_actor(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'jsonlog');
        config()->set('logging.channels.json.handler_with', ['stream' => $path]);

        Route::middleware('api')->get('/api/v1/__log-probe', function () {
            Log::channel('json')->info('probe');

            return ['status' => 'ok'];
        });

        $this->getJson('/api/v1/__log-probe')->assertOk();

        $decoded = json_decode(trim((string) file_get_contents($path)), true, 512, JSON_THROW_ON_ERROR);
        unlink($path);

        $this->assertSame('guest', $decoded['actor_type']);
        $this->assertNull($decoded['actor_id']);
        $this->assertNotNull($decoded['request_id'], 'An in-flight request must contribute a correlation id.');
    }

    /**
     * Builds a Monolog logger with exactly the processor and formatter the json
     * channel uses, and returns the decoded single line it emitted.
     *
     * @param  callable(Logger): void  $emit
     * @return array<string, mixed>
     */
    private function captureLine(callable $emit): array
    {
        $handler = new TestHandler(Level::Debug);
        $handler->setFormatter(new JsonLogFormatter);

        $logger = new Logger('test', [$handler], [$this->app->make(JsonContextProcessor::class)]);

        $emit($logger);

        $records = $handler->getRecords();
        $this->assertCount(1, $records);

        $formatted = $handler->getFormatter()->format($records[0]);

        return json_decode($formatted, true, 512, JSON_THROW_ON_ERROR);
    }
}
