<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * What every ticket notification has in common.
 *
 * THE RULE THIS CLASS EXISTS FOR: a notification renders in the RECIPIENT's
 * language, not the sender's. A supervisor who works in English assigning a
 * ticket to a colleague who works in Arabic must not send them an English
 * email — and the mistake is invisible to the person making it, because their
 * own screen looks right.
 *
 * Laravel resolves `locale()` per recipient at send time, so the same
 * notification object addressed to two people produces two languages.
 *
 * Queued, always. A trigger is somebody pressing Save; waiting on an SMTP
 * handshake to tell them it worked would make a slow mail provider look like a
 * slow application.
 */
abstract class TicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $ticketId,
        public readonly string $reference,
        public readonly string $subject,
    ) {}

    /**
     * Both channels, for every trigger.
     *
     * `database` is what the bell reads; `mail` is what reaches somebody who is
     * not looking at the application. There is deliberately no per-type
     * preference matrix — three triggers is few enough that "which of these do
     * you want?" is a question nobody needs asked.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** The translation key prefix, e.g. `notifications.assigned`. */
    abstract protected function key(): string;

    /**
     * Placeholders for the subject and body lines.
     *
     * @return array<string, string>
     */
    protected function replacements(object $notifiable): array
    {
        return ['reference' => $this->reference, 'subject' => $this->subject];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $locale = $this->localeFor($notifiable);

        return [
            'ticket_id' => $this->ticketId,
            'reference' => $this->reference,
            /*
             * Rendered NOW, in the recipient's language, and stored that way.
             * Storing a key and translating at read time sounds tidier and is
             * wrong: the notification is a record of something that was said to
             * this person, and it should not change wording because they later
             * switched language — or vanish because somebody renamed a key.
             */
            'text' => __("{$this->key()}.line", $this->replacements($notifiable), $locale),
            'kind' => $this->key(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $this->localeFor($notifiable);
        $replacements = $this->replacements($notifiable);

        /*
         * No `->locale()` on the message: MailMessage has no such method, and
         * the locale is a property of the RECIPIENT. `User` declares
         * `HasLocalePreference`, so Laravel renders the whole notification in
         * their language; the explicit `$locale` arguments below make each
         * string say so at the point it is translated rather than relying on
         * ambient state.
         */
        return (new MailMessage)
            ->subject(__("{$this->key()}.subject", $replacements, $locale))
            ->greeting(__('notifications.greeting', ['name' => $this->nameFor($notifiable)], $locale))
            ->line(__("{$this->key()}.line", $replacements, $locale))
            ->action(
                __("{$this->key()}.action", [], $locale),
                rtrim((string) config('app.frontend_url'), '/').'/tickets/'.$this->ticketId,
            )
            ->salutation(__('notifications.signoff', [], $locale));
    }

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
