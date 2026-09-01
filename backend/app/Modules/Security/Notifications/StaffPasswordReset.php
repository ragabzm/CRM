<?php

declare(strict_types=1);

namespace App\Modules\Security\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The staff password-reset email.
 *
 * The link points at the FRONTEND, not the API: the reset form is a Next.js
 * route, and the API has no page to land on. The token travels in the URL and
 * is never stored in plaintext — the database holds a hash, so a database
 * disclosure does not hand over working reset links.
 */
final class StaffPasswordReset extends Notification
{
    use Queueable;

    public function __construct(
        #[\SensitiveParameter] private readonly string $token,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject(__('auth.reset.subject'))
            ->greeting(__('auth.reset.greeting'))
            ->line(__('auth.reset.intro'))
            ->action(__('auth.reset.action'), $this->resetUrl($notifiable))
            ->line(__('auth.reset.expiry', ['count' => $minutes]))
            ->line(__('auth.reset.ignore'));
    }

    private function resetUrl(object $notifiable): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        return $base.'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
