<?php

declare(strict_types=1);

namespace App\Modules\Platform\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

/**
 * Monolog's JsonFormatter nests processor output under "extra". Log shippers and
 * the acceptance criteria both want request_id/actor_type/actor_id/module/
 * ticket_id as top-level keys, so this formatter hoists exactly those five and
 * leaves everything else where Monolog put it.
 */
final class JsonLogFormatter extends JsonFormatter
{
    /**
     * @return array<string, mixed>
     */
    protected function normalizeRecord(LogRecord $record): array
    {
        $normalized = parent::normalizeRecord($record);

        $hoisted = [];
        foreach (JsonContextProcessor::FIELDS as $field) {
            // Present-but-null is deliberate: a consumer can rely on the key
            // existing on every line.
            $hoisted[$field] = $normalized['extra'][$field] ?? null;
            unset($normalized['extra'][$field]);
        }

        return [...$normalized, ...$hoisted];
    }
}
