<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Tickets\Domain\Enum\TicketChannel;
use App\Modules\Tickets\Domain\Priority;

final class CreateTicketInput
{
    public function __construct(
        public readonly string $subject,
        public readonly string $description,
        public readonly string $customerId,
        public readonly TicketChannel $channel,
        public readonly ?int $categoryId = null,
        public readonly ?Priority $priority = null,
        public readonly ?int $departmentId = null,
        /**
         * True when the ticket was opened by a machine-generated email.
         *
         * The one thing this changes: no acknowledgement is sent. Replying to
         * an auto-reply produces an auto-reply, and two systems will do that to
         * each other until somebody notices the mailbox.
         */
        public readonly bool $suppressAcknowledgement = false,
    ) {}
}
