<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Platform\Http\ProblemDetails;
use PHPUnit\Framework\TestCase;

/**
 * Exactly one place in the system writes an error body: ProblemDetailsHandler.
 *
 * If a controller can hand-roll a 4xx, the RFC 9457 shape stops being a
 * guarantee and becomes a convention — and the generated client is built
 * assuming it is a guarantee.
 */
final class NoControllerErrorBodiesTest extends TestCase
{
    /**
     * Matches a JSON response constructed with an explicit 4xx/5xx status:
     * response()->json($x, 404), new JsonResponse($x, 422), ->setStatusCode(500).
     */
    private const ERROR_BODY_PATTERNS = [
        '/response\s*\(\s*\)\s*->\s*json\s*\((?:[^;]*?),\s*(4\d{2}|5\d{2})\b/s',
        '/new\s+JsonResponse\s*\((?:[^;]*?),\s*(4\d{2}|5\d{2})\b/s',
        '/->\s*setStatusCode\s*\(\s*(4\d{2}|5\d{2})\b/s',
        '/response\s*\(\s*\)\s*->\s*setStatusCode\s*\(\s*(4\d{2}|5\d{2})\b/s',
    ];

    public function test_no_module_http_layer_writes_an_error_body(): void
    {
        $violations = [];

        foreach (SourceScanner::phpFiles('app/Modules') as $file) {
            if (! str_contains($file, '/Http/')) {
                continue;
            }

            // The handler is the sanctioned exception; it is what everything
            // else is being kept away from.
            if (str_ends_with($file, 'ProblemDetailsHandler.php')) {
                continue;
            }

            $source = (string) file_get_contents($file);

            foreach (self::ERROR_BODY_PATTERNS as $pattern) {
                if (preg_match($pattern, $source, $m) === 1) {
                    $violations[] = sprintf(
                        '%s builds a %s response directly — throw a ProblemException instead.',
                        str_replace(SourceScanner::basePath().'/', '', $file),
                        $m[1]
                    );
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /** Guards the guard. */
    public function test_the_detection_matches_known_bad_code(): void
    {
        $samples = [
            'return response()->json(["error" => "nope"], 404);',
            'return new JsonResponse(["error" => "nope"], 422);',
            'return $response->setStatusCode(500);',
        ];

        foreach ($samples as $sample) {
            $matched = false;

            foreach (self::ERROR_BODY_PATTERNS as $pattern) {
                if (preg_match($pattern, $sample) === 1) {
                    $matched = true;
                    break;
                }
            }

            $this->assertTrue($matched, "The scan failed to flag: {$sample}");
        }
    }

    public function test_success_responses_are_not_flagged(): void
    {
        $sample = 'return response()->json(["status" => "ok"], 200);';

        foreach (self::ERROR_BODY_PATTERNS as $pattern) {
            $this->assertSame(0, preg_match($pattern, $sample), 'A 2xx response must not be flagged.');
        }
    }

    public function test_every_seeded_platform_code_is_well_formed(): void
    {
        foreach (ProblemDetails::PLATFORM_CODES as $code) {
            $this->assertMatchesRegularExpression(ProblemDetails::CODE_PATTERN, $code);
            $this->assertStringStartsWith('platform.', $code);
        }
    }
}
