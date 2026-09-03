<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\User;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Notifications\SlaWarning;
use App\Modules\Tickets\Notifications\TicketAssigned;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * A notification is written in the RECIPIENT's language.
 *
 * Not the sender's, and not the application's. A supervisor who works in
 * English assigning a ticket to a colleague who works in Arabic must not send
 * them an English email — and the mistake is invisible to the person making it,
 * because their own screen looks right.
 */
final class NotificationLocaleTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->app->make(SettingsRegistry::class)->set('email.enabled', false, null);
    }

    private function reader(string $locale): User
    {
        $user = $this->makeUser(Roles::AGENT);
        $user->forceFill(['preferred_locale' => $locale])->save();

        return $user->refresh();
    }

    private function assigned(): TicketAssigned
    {
        return new TicketAssigned('01T1', 'TKT-000042', 'Invoice is wrong', 'Dana Faris');
    }

    public function test_an_english_reader_gets_english(): void
    {
        $text = $this->assigned()->toArray($this->reader('en'))['text'];

        $this->assertStringContainsString('assigned', $text);
    }

    public function test_an_arabic_reader_gets_arabic(): void
    {
        $text = $this->assigned()->toArray($this->reader('ar'))['text'];

        $this->assertStringContainsString('خصّص', $text);
    }

    public function test_the_senders_language_does_not_leak_in(): void
    {
        // The application locale set to English while an Arabic reader is
        // notified — which is exactly the case a naive implementation gets
        // wrong.
        app()->setLocale('en');

        $text = $this->assigned()->toArray($this->reader('ar'))['text'];

        $this->assertStringContainsString('خصّص', $text);
        $this->assertStringNotContainsString('assigned', $text);
    }

    public function test_an_account_with_no_preference_gets_english(): void
    {
        $user = $this->makeUser(Roles::AGENT);
        $user->forceFill(['preferred_locale' => null])->save();

        // A default, not a preference anybody expressed.
        $this->assertStringContainsString('assigned', $this->assigned()->toArray($user->refresh())['text']);
    }

    public function test_the_email_subject_is_in_the_readers_language(): void
    {
        $mail = $this->assigned()->toMail($this->reader('ar'));

        $this->assertStringContainsString('اتخصّصت', (string) $mail->subject);
    }

    public function test_the_email_carries_a_link_to_the_ticket(): void
    {
        $mail = $this->assigned()->toMail($this->reader('en'));

        // A notification with no way to act on it is a notification that makes
        // somebody go and find the ticket by hand.
        $this->assertStringContainsString('/tickets/01T1', (string) $mail->actionUrl);
    }

    public function test_a_stored_notification_keeps_the_wording_it_was_sent_with(): void
    {
        $reader = $this->reader('ar');

        $stored = $this->assigned()->toArray($reader);

        // Later the person switches to English.
        $reader->forceFill(['preferred_locale' => 'en'])->save();

        /*
         * The stored text does not change. A notification is a record of
         * something that was said to this person; re-translating it on read
         * would rewrite history — and would break the moment somebody renamed
         * a translation key.
         */
        $this->assertStringContainsString('خصّص', $stored['text']);
    }

    public function test_durations_use_western_digits_in_both_languages(): void
    {
        $warning = new SlaWarning('01T1', 'TKT-000042', 'Invoice', SlaWarning::BREACHED, 'response', 75);

        foreach (['en', 'ar'] as $locale) {
            $text = $warning->toArray($this->reader($locale))['text'];

            /*
             * A duration a colleague reads out over the phone has to be
             * quotable between an Arabic reader and an English one.
             * Arabic-Indic numerals make the same number look like two
             * different facts.
             */
            $this->assertStringContainsString('75', $text, "in {$locale}");
            $this->assertDoesNotMatchRegularExpression('/[٠-٩]/u', $text, "in {$locale}");
        }
    }

    public function test_the_timer_is_named_in_the_readers_language(): void
    {
        $warning = new SlaWarning('01T1', 'TKT-000042', 'Invoice', SlaWarning::AT_RISK, 'resolution', 10);

        // Not a raw key an agent would have to learn.
        $this->assertStringContainsString('resolution', $warning->toArray($this->reader('en'))['text']);
        $this->assertStringContainsString('الحل', $warning->toArray($this->reader('ar'))['text']);
    }
}
