<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain;

use App\Modules\Tickets\Contracts\InboundProvenance;
use Illuminate\Support\Facades\DB;

/**
 * Reads what the intake recorded when the message arrived.
 *
 * One query for the whole conversation, because that is how it is read.
 */
final class InboundMessageProvenance implements InboundProvenance
{
    public function forMessages(array $messageIds): array
    {
        if ($messageIds === []) {
            return [];
        }

        return DB::table('inbound_messages')
            ->whereIn('message_id', $messageIds)
            ->get(['message_id', 'channel', 'correlation_reason'])
            ->mapWithKeys(static fn (object $row): array => [
                (string) $row->message_id => [
                    'channel' => (string) $row->channel,
                    'correlation_reason' => $row->correlation_reason === null
                        ? null
                        : (string) $row->correlation_reason,
                ],
            ])
            ->all();
    }
}
