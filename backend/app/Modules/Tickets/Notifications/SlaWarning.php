<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Notifications;

/**
 * "This is about to be late", or "this is late".
 *
 * One class for both, because they differ only in wording and urgency, and two
 * near-identical classes would drift the moment somebody edited one.
 *
 * The at-risk one is the one that matters: a warning that arrives with the
 * breach is not a warning, it is a report.
 */
final class SlaWarning extends TicketNotification
{
    public const AT_RISK = 'at_risk';

    public const BREACHED = 'breached';

    public function __construct(
        string $ticketId,
        string $reference,
        string $subject,
        private readonly string $state,
        /** `response` | `resolution` — which promise is in trouble. */
        private readonly string $timer,
        /** Minutes left, or minutes over once breached. Always positive. */
        private readonly int $minutes,
    ) {
        parent::__construct($ticketId, $reference, $subject);
    }

    protected function key(): string
    {
        return $this->state === self::BREACHED
            ? 'notifications.sla_breached'
            : 'notifications.sla_at_risk';
    }

    /**
     * @return array<string, string>
     */
    protected function replacements(object $notifiable): array
    {
        $locale = $this->localeFor($notifiable);

        return [
            ...parent::replacements($notifiable),
            // Which promise, named in the recipient's language rather than as
            // a raw key an agent would have to learn.
            'timer' => __("notifications.timer.{$this->timer}", [], $locale),
            /*
             * Western digits, deliberately, in both languages. A duration a
             * colleague reads out over the phone has to be quotable between an
             * Arabic reader and an English one, and Arabic-Indic numerals make
             * the same number look like two different facts.
             */
            'minutes' => (string) $this->minutes,
        ];
    }
}
