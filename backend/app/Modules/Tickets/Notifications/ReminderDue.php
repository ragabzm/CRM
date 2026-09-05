<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "You asked to be reminded about this."
 *
 * NOT a `TicketNotification`, because a reminder can be set on a standalone
 * task — there is no ticket, no reference and no subject to carry. What it
 * does share is the rule that matters: it renders in the recipient's language,
 * through the same two channels Story 5.4 established, with no per-type
 * preference matrix, no digest, no quiet hours and no snooze.
 *
 * There is exactly one of these per reminder, for ever. The sweep marks it
 * fired in the same transaction that sends it, so nothing here has to be
 * defensive about being called twice.
 */
final class ReminderDue extends Notification implements ShouldQueue
{
    use InTheRecipientsLanguage;
    use Queueable;

    public function __construct(
        public readonly string $reminderId,
        /** What it is about, in the owner's own words — a ticket subject or a task title. */
        public readonly string $about,
        public readonly ?string $ticketId,
        public readonly ?string $reference,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $locale = $this->localeFor($notifiable);

        return [
            'reminder_id' => $this->reminderId,
            'ticket_id' => $this->ticketId,
            'reference' => $this->reference,
            // Rendered now, in their language, and stored that way — a record
            // of something that was said, not a key to translate later.
            'text' => __('notifications.reminder.line', ['about' => $this->about], $locale),
            'kind' => 'notifications.reminder',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $this->localeFor($notifiable);

        return (new MailMessage)
            ->subject(__('notifications.reminder.subject', ['about' => $this->about], $locale))
            ->greeting(__('notifications.greeting', ['name' => $this->nameFor($notifiable)], $locale))
            ->line(__('notifications.reminder.line', ['about' => $this->about], $locale))
            ->action(__('notifications.reminder.action', [], $locale), $this->deepLink())
            ->salutation(__('notifications.signoff', [], $locale));
    }

    /**
     * The ticket if there is one, and Home's own tab if there is not.
     *
     * A reminder on a standalone task has nowhere else to go — and sending
     * somebody to a ticket list to look for a task would be worse than sending
     * them to the tab the task is actually on.
     */
    private function deepLink(): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        return $this->ticketId === null
            ? $base.'/?tab=tasks'
            : $base.'/tickets/'.$this->ticketId;
    }
}
