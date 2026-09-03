<?php

declare(strict_types=1);

namespace App\Modules\Portal\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Here is how to get back in."
 *
 * A separate notification from the staff one, for two reasons that both matter:
 *
 * The LINK points at the portal's reset page, which lives in a different shell
 * from the staff form. Laravel's default would send a customer to a sign-in
 * screen built for agents, which they cannot use and which tells them the
 * product is not for them.
 *
 * The LANGUAGE is the customer's own. Somebody who registered in Arabic and
 * receives English instructions for recovering their account has been locked
 * out twice.
 */
final class PortalPasswordReset extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly string $email,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        // Mail only. A reset link in a notification bell would be useless —
        // the whole situation is that they cannot get in to see it.
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = method_exists($notifiable, 'preferredLocale') ? $notifiable->preferredLocale() : 'en';

        return (new MailMessage)
            ->subject(__('portal.password_reset.subject', [], $locale))
            ->greeting(__('portal.password_reset.greeting', [], $locale))
            ->line(__('portal.password_reset.line', [], $locale))
            ->action(__('portal.password_reset.action', [], $locale), $this->resetUrl())
            ->line(__('portal.password_reset.expiry', [
                'minutes' => (string) config('auth.passwords.portal_accounts.expire', 60),
            ], $locale))
            // Said plainly, because a reset email nobody asked for is the first
            // sign somebody else is trying to get in.
            ->line(__('portal.password_reset.ignore', [], $locale))
            ->salutation(__('portal.password_reset.signoff', [], $locale));
    }

    private function resetUrl(): string
    {
        return sprintf(
            '%s/portal/reset-password?token=%s&email=%s',
            rtrim((string) config('app.frontend_url'), '/'),
            $this->token,
            urlencode($this->email),
        );
    }
}
