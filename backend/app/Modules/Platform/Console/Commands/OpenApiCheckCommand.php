<?php

declare(strict_types=1);

namespace App\Modules\Platform\Console\Commands;

use Dedoc\Scramble\Generator;
use Illuminate\Console\Command;

/**
 * Fails when the committed openapi.yaml no longer matches the routes.
 *
 * This is the guard that makes the generated TypeScript client trustworthy: if
 * a route changes and nobody regenerates, CI stops the merge here rather than
 * letting the frontend drift silently.
 */
final class OpenApiCheckCommand extends Command
{
    protected $signature = 'openapi:check {--path=openapi.yaml : The committed document to compare against}';

    protected $description = 'Fail if backend/openapi.yaml is stale relative to the registered routes.';

    public function handle(Generator $generator): int
    {
        $path = base_path((string) $this->option('path'));

        if (! is_file($path)) {
            $this->error("Missing {$path}. Run: composer openapi:generate");

            return self::FAILURE;
        }

        $committed = (string) file_get_contents($path);
        $fresh = OpenApiGenerateCommand::render($generator);

        if ($committed === $fresh) {
            $this->info('openapi.yaml is up to date.');

            return self::SUCCESS;
        }

        $this->error('openapi.yaml is stale — it does not match the current routes.');
        $this->line('Remediation: run `composer openapi:generate` and commit the result.');
        $this->newLine();
        $this->line($this->diff($committed, $fresh));

        return self::FAILURE;
    }

    /**
     * A unified-ish line diff. Kept in PHP rather than shelling out to diff(1)
     * so the CI output is identical on every platform.
     */
    private function diff(string $committed, string $fresh): string
    {
        $a = explode("\n", $committed);
        $b = explode("\n", $fresh);
        $lines = [];

        for ($i = 0, $n = max(count($a), count($b)); $i < $n; $i++) {
            $left = $a[$i] ?? null;
            $right = $b[$i] ?? null;

            if ($left === $right) {
                continue;
            }

            if ($left !== null) {
                $lines[] = sprintf('- %d: %s', $i + 1, $left);
            }
            if ($right !== null) {
                $lines[] = sprintf('+ %d: %s', $i + 1, $right);
            }
        }

        return implode("\n", array_slice($lines, 0, 80));
    }
}
