<?php

declare(strict_types=1);

namespace Tests\Unit\Customers;

use App\Modules\Customers\Domain\ContactKind;
use App\Modules\Customers\Domain\IdentifierNormaliser;
use PHPUnit\Framework\TestCase;

final class IdentifierNormaliserTest extends TestCase
{
    private function email(string $value): string
    {
        return IdentifierNormaliser::normalise(ContactKind::Email, $value);
    }

    private function phone(string $value): string
    {
        return IdentifierNormaliser::normalise(ContactKind::Phone, $value);
    }

    public function test_an_email_is_lowercased_and_trimmed(): void
    {
        $this->assertSame('hana@example.test', $this->email('  Hana@Example.TEST  '));
    }

    public function test_an_email_survives_a_paste_from_a_mail_client(): void
    {
        // "Hana Yousef <hana@example.test>" pastes leave stray whitespace that
        // would otherwise make two identical addresses look different.
        $this->assertSame('hana@example.test', $this->email("hana@example.test\n"));
        $this->assertSame('hana@example.test', $this->email('hana @example.test'));
    }

    public function test_the_same_number_written_two_ways_collapses_to_one_key(): void
    {
        // The case the whole mechanism exists for: an agent types what the
        // customer says, and the customer says it differently each time.
        $this->assertSame($this->phone('+44 20 7946 0958'), $this->phone('020 7946 0958'));
        $this->assertSame($this->phone('(555) 123-4567'), $this->phone('+1 555 123 4567'));
    }

    public function test_punctuation_and_spacing_are_irrelevant(): void
    {
        $this->assertSame($this->phone('555.123.4567'), $this->phone('555 123 4567'));
        $this->assertSame($this->phone('555-123-4567'), $this->phone('5551234567'));
    }

    public function test_it_compares_the_trailing_digits(): void
    {
        // The country code and trunk prefix sit at the FRONT, and the front is
        // exactly what differs between two ways of writing one number.
        $this->assertSame('7946095812', $this->phone('+44 20 7946 0958 12'));
        $this->assertSame(IdentifierNormaliser::PHONE_COMPARISON_DIGITS, strlen($this->phone('+44 20 7946 0958 12')));
    }

    public function test_a_short_number_is_kept_whole(): void
    {
        // An extension or a short code has fewer than ten digits; truncating
        // from the end would leave nothing to compare.
        $this->assertSame('4567', $this->phone('4567'));
    }

    public function test_a_value_with_no_digits_normalises_to_nothing(): void
    {
        // Which is how the controller knows to refuse it rather than storing an
        // identifier nobody can be reached on.
        $this->assertSame('', $this->phone('call the office'));
        $this->assertSame('', $this->phone('---'));
    }

    public function test_an_empty_email_normalises_to_nothing(): void
    {
        $this->assertSame('', $this->email('   '));
    }

    public function test_normalisation_is_stable(): void
    {
        // Running it twice must not change the answer, or a re-import would
        // stop matching what is already stored.
        $once = $this->phone('+44 20 7946 0958');
        $this->assertSame($once, $this->phone($once));

        $emailOnce = $this->email('Hana@Example.test');
        $this->assertSame($emailOnce, $this->email($emailOnce));
    }

    public function test_different_people_stay_different(): void
    {
        $this->assertNotSame($this->email('hana@example.test'), $this->email('omar@example.test'));
        $this->assertNotSame($this->phone('555 123 4567'), $this->phone('555 123 4568'));
    }
}
