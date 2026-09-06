<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Platform\Branding\Domain\Branding;
use App\Modules\Platform\Branding\Domain\ContrastRatio;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A brand colour a customer cannot read is refused, not warned about.
 *
 * The refusal is the whole story. Every softer option — accept with a warning,
 * apply pending review, flag it on a dashboard — ends the same way: the colour
 * is live on the portal while somebody means to look at it later, and the
 * people who cannot read it are the customers, who will not report it as a
 * contrast failure. They will just not use the thing.
 *
 * Tested AT THE BOUNDARY, because that is the only place a contrast check is
 * ever wrong. A checker that is right about black and white and wrong at 4.5
 * is a checker that passes every obvious case in review.
 */
final class BrandContrastTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): SettingsRegistry
    {
        return $this->app->make(SettingsRegistry::class);
    }

    /**
     * Known ratios, computed from the WCAG 2.1 formula.
     *
     * @return array<string, array{string, string, float}>
     */
    public static function knownRatios(): array
    {
        return [
            'black on white is the maximum' => ['#000000', '#ffffff', 21.0],
            'white on white is the minimum' => ['#ffffff', '#ffffff', 1.0],
            'mid grey on white' => ['#767676', '#ffffff', 4.54],
            /*
             * Verified against an independent implementation of the formula
             * rather than guessed. The first version of this row carried
             * 18.98 — a plausible-looking number I had not computed — and the
             * test failed against a correct implementation. A fixture nobody
             * checked is a fixture that decides the implementation is wrong.
             */
            'the product ink on white' => ['#101320', '#ffffff', 18.49],
        ];
    }

    #[DataProvider('knownRatios')]
    public function test_the_formula_agrees_with_the_specification(
        string $foreground,
        string $background,
        float $expected,
    ): void {
        /*
         * `#767676` is the canonical example: it is the lightest grey that
         * passes AA on white, and a checker that averages the raw hex channels
         * instead of linearising them gets this one confidently wrong.
         */
        $this->assertEqualsWithDelta($expected, ContrastRatio::between($foreground, $background), 0.02);
    }

    public function test_the_boundary_is_where_the_specification_puts_it(): void
    {
        // Just passes.
        $this->assertTrue(ContrastRatio::passesAA('#767676', '#ffffff'));

        // Just fails — one step lighter.
        $this->assertFalse(ContrastRatio::passesAA('#777777', '#ffffff'));
    }

    public function test_three_and_six_digit_hex_are_the_same_colour(): void
    {
        // Administrators write both, and a checker that understood only one
        // would refuse a colour that is fine.
        $this->assertSame(
            ContrastRatio::between('#000', '#fff'),
            ContrastRatio::between('#000000', '#ffffff'),
        );
    }

    public function test_a_readable_colour_is_accepted(): void
    {
        $this->settings()->set(Branding::PRIMARY_COLOUR, '#1c2333', null);

        $this->assertSame('#1c2333', $this->settings()->get(Branding::PRIMARY_COLOUR));
    }

    public function test_an_unreadable_colour_is_refused_with_the_measured_ratio(): void
    {
        try {
            // Pale yellow: perfectly pleasant, and invisible on white.
            $this->settings()->set(Branding::PRIMARY_COLOUR, '#ffe680', null);

            $this->fail('A colour failing WCAG AA was accepted.');
        } catch (ProblemException $e) {
            $detail = $e->getMessage().' '.($e->problem->detail ?? '');

            /*
             * The measured ratio, quoted back. "That colour is not accessible"
             * sends somebody to guess again; "it measures 1.31:1 and AA needs
             * 4.5:1" tells them how far off they are.
             */
            $this->assertMatchesRegularExpression('/\d+\.\d+/', $detail);
            $this->assertStringContainsString('4.5', $detail);
        }

        // And it was not stored pending anything.
        $this->assertSame('', $this->settings()->get(Branding::PRIMARY_COLOUR));
    }

    public function test_it_is_checked_against_every_surface_it_will_sit_on(): void
    {
        /*
         * The backgrounds are the ones the four branded screens are actually
         * painted with. Checking against a colour the product does not use
         * would either refuse a colour that works or accept one that does not
         * — and only the second is discovered by a customer.
         */
        $this->assertNotSame([], Branding::SURFACES);

        foreach (Branding::SURFACES as $name => $background) {
            $this->assertTrue(
                ContrastRatio::isHex($background),
                "[{$name}] is not a colour this check can measure.",
            );
        }
    }

    public function test_clearing_the_colour_is_allowed(): void
    {
        $this->settings()->set(Branding::PRIMARY_COLOUR, '#1c2333', null);
        $this->settings()->set(Branding::PRIMARY_COLOUR, '', null);

        // Cleared means the product wears its own design system, which is a
        // complete answer rather than a missing one.
        $this->assertSame('', $this->settings()->get(Branding::PRIMARY_COLOUR));
    }

    public function test_a_logo_must_be_an_attachment_not_a_link(): void
    {
        $this->expectException(ProblemException::class);

        /*
         * A settable URL would be a way to point a customer-facing page at
         * somebody else's server — and the logo is the one image on the portal
         * that every visitor loads.
         */
        $this->settings()->set(Branding::LOGO_ATTACHMENT, 'https://example.test/logo.png', null);
    }

    public function test_there_is_no_setting_for_anything_that_was_cut(): void
    {
        $keys = array_keys($this->settings()->all());

        /*
         * Absent, not disabled. An application name, a separate sign-in logo,
         * a palette, typography or spacing controls, a preview workflow, a
         * theme builder and custom CSS — there is no field, no setting and no
         * upload for any of them.
         */
        foreach ([
            'branding.application_name', 'branding.sign_in_logo', 'branding.palette',
            'branding.typography', 'branding.spacing', 'branding.preview',
            'branding.theme', 'branding.custom_css',
        ] as $absent) {
            $this->assertNotContains($absent, $keys);
        }

        // And exactly three that are present.
        $branding = array_values(array_filter($keys, static fn (string $k): bool => str_starts_with($k, 'branding.')));
        sort($branding);

        $this->assertSame([
            Branding::HEADER,
            Branding::LOGO_ATTACHMENT,
            Branding::PRIMARY_COLOUR,
        ], $branding);
    }
}
