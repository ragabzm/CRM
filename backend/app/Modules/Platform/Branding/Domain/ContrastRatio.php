<?php

declare(strict_types=1);

namespace App\Modules\Platform\Branding\Domain;

/**
 * WCAG 2.1 relative luminance, and the ratio between two colours.
 *
 * ONE function, on the SERVER, and it is the enforcement point. A check in the
 * browser is presentation: it tells an administrator what is about to happen
 * and it can be skipped by anybody who calls the API directly — which is
 * exactly the person who would be pasting a brand colour in from a script.
 *
 * The formula is written out rather than pulled from a package because it is
 * eleven lines and a dependency here would be a dependency that decides what
 * "accessible" means on our behalf. It is testable at the boundary, which is
 * the only place a contrast check is ever wrong.
 *
 * @see https://www.w3.org/TR/WCAG21/#dfn-relative-luminance
 */
final class ContrastRatio
{
    /** WCAG AA for normal text. */
    public const AA = 4.5;

    /**
     * The ratio between two colours, from 1 (identical) to 21 (black on white).
     */
    public static function between(string $foreground, string $background): float
    {
        $light = self::luminance($foreground);
        $dark = self::luminance($background);

        if ($light < $dark) {
            [$light, $dark] = [$dark, $light];
        }

        /*
         * Rounded to two places at the boundary rather than left raw. The
         * refusal message quotes this number back to an administrator, and
         * "4.4999999" beside a threshold of 4.5 reads as a bug in the checker
         * rather than as a colour that just missed.
         */
        return round(($light + 0.05) / ($dark + 0.05), 2);
    }

    public static function passesAA(string $foreground, string $background): bool
    {
        return self::between($foreground, $background) >= self::AA;
    }

    /**
     * Relative luminance, per the WCAG definition.
     */
    private static function luminance(string $hex): float
    {
        [$r, $g, $b] = self::channels($hex);

        return 0.2126 * self::linear($r) + 0.7152 * self::linear($g) + 0.0722 * self::linear($b);
    }

    /**
     * Undoes the sRGB transfer curve.
     *
     * The reason a contrast ratio cannot be eyeballed from the hex values:
     * the channels are gamma-encoded, and averaging them the way they are
     * written gives an answer that is confidently wrong in the middle of the
     * range — which is exactly where brand colours live.
     */
    private static function linear(float $channel): float
    {
        return $channel <= 0.04045
            ? $channel / 12.92
            : (($channel + 0.055) / 1.055) ** 2.4;
    }

    /**
     * @return array{float, float, float} Each 0..1.
     */
    private static function channels(string $hex): array
    {
        $clean = ltrim(trim($hex), '#');

        // `#abc` is the same colour as `#aabbcc`, and administrators write it.
        if (strlen($clean) === 3) {
            $clean = $clean[0].$clean[0].$clean[1].$clean[1].$clean[2].$clean[2];
        }

        return [
            hexdec(substr($clean, 0, 2)) / 255,
            hexdec(substr($clean, 2, 2)) / 255,
            hexdec(substr($clean, 4, 2)) / 255,
        ];
    }

    public static function isHex(string $value): bool
    {
        return preg_match('/^#?(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', trim($value)) === 1;
    }
}
