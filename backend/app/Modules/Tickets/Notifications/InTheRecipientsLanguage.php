<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Notifications;

/**
 * The rule every notification in this module obeys.
 *
 * A notification renders in the RECIPIENT's language, not the sender's. A
 * supervisor who works in English mentioning a colleague who works in Arabic
 * must not send them an English email — and the mistake is invisible to the
 * person making it, because their own screen looks right.
 *
 * Extracted from `TicketNotification` when reminders arrived: a reminder on a
 * standalone task has no ticket, no reference and no subject, so it cannot
 * extend that base — but it must not get its own, second answer to "whose
 * language is this in?"
 */
trait InTheRecipientsLanguage
{
    /**
     * The recipient's language, defaulting to English.
     *
     * A default, not a preference anybody expressed — which is why it is
     * resolved here rather than written onto the account.
     */
    protected function localeFor(object $notifiable): string
    {
        return method_exists($notifiable, 'preferredLocale')
            ? $notifiable->preferredLocale()
            : 'en';
    }

    protected function nameFor(object $notifiable): string
    {
        $name = $notifiable->getAttribute('name');

        return is_string($name) && $name !== '' ? $name : '';
    }
}
