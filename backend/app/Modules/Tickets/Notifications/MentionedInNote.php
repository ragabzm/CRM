<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Notifications;

/**
 * "A colleague asked for you by name."
 *
 * A mention notifies, and does NOTHING ELSE. It does not subscribe you to the
 * ticket, add you to a followers list, or grant you any visibility you did not
 * already have — department is not an access boundary in this product, so
 * there is nothing to widen and no permission to leak. Every one of those is a
 * feature somebody expects here; every one of them turns "look at this" into
 * a standing relationship nobody remembers agreeing to.
 */
final class MentionedInNote extends TicketNotification
{
    public function __construct(
        string $ticketId,
        string $reference,
        string $subject,
        private readonly string $actorName,
        private readonly string $messageId,
    ) {
        parent::__construct($ticketId, $reference, $subject);
    }

    protected function key(): string
    {
        return 'notifications.mentioned';
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(object $notifiable): array
    {
        return [...parent::replacements($notifiable), 'actor' => $this->actorName];
    }

    /**
     * The ticket, opened AT THE NOTE.
     *
     * Landing on the ticket alone leaves somebody scrolling a long thread
     * looking for the sentence that named them — which is the whole content of
     * the notification they just received.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [...parent::toArray($notifiable), 'message_id' => $this->messageId];
    }

    protected function deepLink(): string
    {
        return parent::deepLink().'#note-'.$this->messageId;
    }
}
