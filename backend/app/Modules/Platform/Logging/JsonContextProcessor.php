<?php

declare(strict_types=1);

namespace App\Modules\Platform\Logging;

use App\Modules\Platform\Support\RequestContext;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Injects the five mandatory structured-logging fields into every record.
 *
 * `ticket_id` is the one field feature code sets per-call, via
 * Log::withContext(['ticket_id' => ...]); that per-record value wins over the
 * ambient one so a job handling several tickets logs the right id each time.
 */
final class JsonContextProcessor implements ProcessorInterface
{
    /** Fields hoisted to the top level of the emitted JSON object. */
    public const FIELDS = ['request_id', 'actor_type', 'actor_id', 'module', 'ticket_id'];

    public function __construct(
        private readonly RequestContext $context,
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $fields = $this->context->toLogFields();
        $consumed = [];

        foreach (self::FIELDS as $field) {
            if (array_key_exists($field, $record->context)) {
                $value = $record->context[$field];
                $fields[$field] = $value === null ? null : (string) $value;
                $consumed[] = $field;
            }
        }

        // Drop the keys we hoisted so they are not emitted twice.
        $context = $record->context;
        foreach ($consumed as $field) {
            unset($context[$field]);
        }

        return $record->with(
            context: $context,
            extra: [...$record->extra, ...$fields],
        );
    }
}
