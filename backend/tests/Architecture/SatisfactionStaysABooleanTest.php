<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Tickets\Domain\Concurrency\VersionGuard;
use PHPUnit\Framework\TestCase;

/**
 * Satisfaction stays a boolean the customer owns.
 *
 * Two rules, and both are the kind that erode rather than break.
 *
 * The first is that there is no SCALE. A five-point rating is the single most
 * requested change to a feature like this, and the first step is always
 * harmless-looking: an integer column "for flexibility", or a `scale_version`
 * so the change can be made later. Either one means every figure afterwards
 * needs a conversion, and comparing two periods becomes an argument about
 * normalisation rather than a fact.
 *
 * The second is that STAFF CANNOT WRITE IT. A satisfaction figure an agent can
 * edit is a figure nobody has any reason to believe — and the edit would not
 * look like tampering, it would look like a helpful correction.
 *
 * If FR-101 is ever re-derived and a scale is genuinely wanted, deleting this
 * file is the deliberate act that says so.
 */
final class SatisfactionStaysABooleanTest extends TestCase
{
    /**
     * Names that would each be the first half of a rating scale.
     *
     * Matched against CODE only, so a comment explaining why there is no scale
     * is not itself a violation of the rule it explains.
     *
     * @var list<string>
     */
    private const FORBIDDEN = [
        'satisfaction_score',
        'satisfaction_scale',
        'scale_version',
        'csat',
        'CsatScale',
        'nps',
        'NetPromoter',
        'star_rating',
        'rating_value',
    ];

    public function test_no_scale_has_appeared(): void
    {
        $found = [];

        foreach (SourceScanner::moduleNames() as $module) {
            foreach (SourceScanner::phpFiles("app/Modules/{$module}") as $file) {
                $code = SourceScanner::codeOnly($file);

                foreach (self::FORBIDDEN as $needle) {
                    if (stripos($code, $needle) !== false) {
                        $found[] = basename($file).' contains '.$needle;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $found,
            "Feedback is a boolean. A scale needs a range, a midpoint and a conversion.\n".implode("\n", $found),
        );
    }

    public function test_the_command_takes_a_boolean_and_could_not_take_a_score(): void
    {
        $rate = new \ReflectionMethod(
            \App\Modules\Tickets\Domain\Commands\RateTicket::class,
            'handle',
        );

        $positive = null;

        foreach ($rate->getParameters() as $parameter) {
            if ($parameter->getName() === 'positive') {
                $positive = $parameter;
            }
        }

        $this->assertNotNull($positive, 'RateTicket lost its `positive` parameter.');

        /*
         * The signature is where the refusal is cheapest to hold: a caller
         * cannot pass a 3 to a `bool`, and nobody has to remember not to.
         */
        $this->assertSame('bool', (string) $positive->getType());
    }

    public function test_staff_have_no_write_path_to_it(): void
    {
        /*
         * `UpdateTicketAttributes` is the ONLY way staff change a ticket
         * property, and it can only reach the five contended ones. Satisfaction
         * is deliberately not among them — which is what makes "staff cannot
         * edit it" structural rather than a missing endpoint somebody could
         * add without noticing.
         */
        $this->assertNotContains('satisfaction', VersionGuard::CONTENDED);
        $this->assertNotContains('satisfaction_comment', VersionGuard::CONTENDED);
    }

    public function test_only_the_portal_gateway_and_the_command_write_it(): void
    {
        $writers = [];

        foreach (SourceScanner::moduleNames() as $module) {
            foreach (SourceScanner::phpFiles("app/Modules/{$module}") as $file) {
                $code = SourceScanner::codeOnly($file);

                if (str_contains($code, "'satisfaction' =>") || str_contains($code, 'satisfaction_at')) {
                    $writers[] = basename($file);
                }
            }
        }

        sort($writers);

        /*
         * Pinned. `RateTicket` writes it; `CustomerRequests` and
         * `TicketResource` read it. A new name here is a new way for the
         * figure to change, and it has to be a decision rather than an import.
         */
        $this->assertSame(
            [
                // The migration that creates the columns.
                '2026_09_27_000100_add_satisfaction_to_tickets.php',
                // Named only because it positions its column after
                // `satisfaction_at`. It writes nothing here.
                '2026_09_27_000200_add_first_finished_at_to_tickets.php',
                'CustomerRequests.php',
                // The emailed invitation. A SECOND door onto the rating, and
                // the reason it is allowed is that it goes through `RateTicket`
                // like the portal does — same window, same lock, same history.
                // Its authorisation is a signature rather than a session.
                'FeedbackInvitationController.php',
                'RateTicket.php',
                'Ticket.php',
                'TicketResource.php',
            ],
            array_values(array_unique($writers)),
        );
    }
}
