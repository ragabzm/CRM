<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Modules\Ai\Domain\Sanitiser\Redactor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What must not leave the building, in both languages.
 *
 * A fixture corpus rather than a handful of examples, and ARABIC NAMES ARE THE
 * POINT of it. A redactor tuned on Latin script passes every English test and
 * leaks on the first Arabic ticket: there is no capitalisation to key on, so
 * the "two capitalised words" heuristic that carries English finds nothing at
 * all and the name goes out intact.
 *
 * Each case asserts BOTH halves — that the personal detail is gone, and that
 * the complaint is still there. A redactor that returns an empty string passes
 * every leak test ever written and is useless.
 */
final class RedactionCorpusTest extends TestCase
{
    private function redactor(): Redactor
    {
        return new Redactor;
    }

    /**
     * @return array<string, array{string, list<string>, list<string>, list<string>}>
     *         text, declared, must-not-appear, must-still-appear
     */
    public static function corpus(): array
    {
        return [
            'english · declared name and the complaint survives' => [
                "Hana Yousef writes: the invoice is wrong.",
                ['Hana Yousef'],
                ['Hana', 'Yousef'],
                ['invoice is wrong'],
            ],

            'arabic · declared name and the complaint survives' => [
                'هناء يوسف تكتب: الفاتورة فيها خطأ.',
                ['هناء يوسف'],
                ['هناء', 'يوسف'],
                ['الفاتورة فيها خطأ'],
            ],

            'arabic · a surname alone is still a name' => [
                'راجعت مع يوسف أمس.',
                ['يوسف'],
                ['يوسف'],
                [],
            ],

            'english · email address' => [
                'Write back to hana.yousef@example.test please.',
                [],
                ['hana.yousef@example.test', '@example.test'],
                ['Write back'],
            ],

            'arabic · email inside arabic prose' => [
                'ابعتوا على hana@example.test من فضلكم.',
                [],
                ['hana@example.test'],
                ['من فضلكم'],
            ],

            'english · phone number' => [
                'Call me on +20 100 123 4567 tomorrow.',
                [],
                ['4567', '100 123'],
                ['tomorrow'],
            ],

            'arabic · phone written with arabic-indic digits' => [
                'رقمي ٠١٠٠١٢٣٤٥٦٧ لو حبيتم تتصلوا.',
                [],
                ['٠١٠٠١٢٣٤٥٦٧'],
                [],
            ],

            'ticket reference' => [
                'This is about TKT-000371 from last week.',
                [],
                ['TKT-000371'],
                ['last week'],
            ],

            'customer reference' => [
                'Account CUS-004512 has the wrong address.',
                [],
                ['CUS-004512'],
                [],
            ],

            'english · postal address is omitted whole' => [
                "The order never arrived.\n14 Nile Street, Building 3, Floor 2\nPlease resend.",
                [],
                ['Nile Street', 'Building 3'],
                ['order never arrived', 'Please resend'],
            ],

            'arabic · postal address is omitted whole' => [
                "الطلب مَوصلش.\n١٤ شارع النيل، عمارة ٣، الدور ٢\nياريت تبعتوه تاني.",
                [],
                ['شارع النيل'],
                ['الطلب مَوصلش', 'ياريت تبعتوه تاني'],
            ],

            'arabic · an undeclared name after an honorific is omitted' => [
                "الفاتورة فيها خطأ.\nكلمت الأستاذ محمود امبارح.\nياريت تراجعوها.",
                [],
                ['محمود'],
                ['الفاتورة فيها خطأ', 'ياريت تراجعوها'],
            ],

            'arabic · a self-introduction is omitted' => [
                "اسمي كريم عبد الله.\nعندي مشكلة في الشحن.",
                [],
                ['كريم'],
                ['مشكلة في الشحن'],
            ],

            'english · an undeclared name after a title is omitted' => [
                "The charge is wrong.\nI spoke to Mr Adams yesterday.\nPlease check.",
                [],
                ['Adams'],
                ['charge is wrong', 'Please check'],
            ],

            'english · a signature line is omitted' => [
                "Please refund me.\nRegards Sarah",
                [],
                ['Sarah'],
                ['Please refund me'],
            ],

            'nothing personal · left completely alone' => [
                'The tracking number does not open and the page shows an error.',
                [],
                [],
                ['tracking number does not open', 'shows an error'],
            ],
        ];
    }

    /**
     * @param  list<string>  $declared
     * @param  list<string>  $mustNotAppear
     * @param  list<string>  $mustSurvive
     */
    #[DataProvider('corpus')]
    public function test_the_corpus_is_redacted(
        string $text,
        array $declared,
        array $mustNotAppear,
        array $mustSurvive,
    ): void {
        $clean = $this->redactor()->redact($text, $declared);

        foreach ($mustNotAppear as $leak) {
            $this->assertStringNotContainsString(
                $leak,
                $clean,
                "[{$leak}] survived redaction.",
            );
        }

        foreach ($mustSurvive as $kept) {
            /*
             * The other half, and the one a leak-only test suite forgets: a
             * redactor that returns an empty string never leaks and never
             * helps. What the agent is actually complaining about has to
             * survive, or the capability above this is worthless.
             */
            $this->assertStringContainsString(
                $kept,
                $clean,
                "[{$kept}] was lost — the complaint has to survive redaction.",
            );
        }
    }

    public function test_it_is_deterministic(): void
    {
        $text = "اسمي كريم عبد الله ورقمي ٠١٠٠١٢٣٤٥٦٧.\nMr Adams said TKT-000371 was closed.";

        $first = $this->redactor()->redact($text, ['كريم عبد الله']);

        for ($i = 0; $i < 20; $i++) {
            /*
             * Twenty runs, same answer. No model, no scoring, no threshold —
             * which is the only way a redactor can be tested at all, and a
             * redactor nobody can test is one nobody should trust with an
             * address.
             */
            $this->assertSame($first, $this->redactor()->redact($text, ['كريم عبد الله']));
        }
    }

    public function test_a_longer_declared_name_is_removed_before_a_shorter_one(): void
    {
        $clean = $this->redactor()->redact('Hana Yousef called.', ['Hana', 'Hana Yousef']);

        /*
         * Shortest-first would leave " Yousef" behind — a surname on its own,
         * which is still a name and reads as a successful redaction.
         */
        $this->assertStringNotContainsString('Yousef', $clean);
    }
}
