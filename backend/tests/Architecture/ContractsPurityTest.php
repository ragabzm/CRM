<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Contracts/ is the only surface one module may depend on from another, so it
 * has to stay dependency-free: the moment a contract imports a concrete class
 * from its own module, every consumer inherits that dependency too.
 */
final class ContractsPurityTest extends TestCase
{
    /**
     * Namespaces a contract may reference besides its own Contracts/ folder.
     * Framework *contracts* are allowed because they are themselves interfaces;
     * framework implementations are not.
     */
    private const ALLOWED_PREFIXES = [
        'Illuminate\\Contracts\\',
        'Psr\\',
        'DateTimeInterface',
        'DateTimeImmutable',
        'Stringable',
        'JsonSerializable',
        'Traversable',
        'Countable',
        'IteratorAggregate',
    ];

    public function test_contracts_import_nothing_outside_themselves(): void
    {
        $violations = [];

        foreach (SourceScanner::moduleNames() as $module) {
            $own = "App\\Modules\\{$module}\\Contracts\\";

            foreach (SourceScanner::phpFiles("app/Modules/{$module}/Contracts") as $file) {
                foreach (SourceScanner::imports($file) as $import) {
                    if (str_starts_with($import, $own) || $this->isAllowed($import)) {
                        continue;
                    }

                    $violations[] = sprintf(
                        '%s imports %s',
                        str_replace(SourceScanner::basePath().'/', '', $file),
                        $import
                    );
                }
            }
        }

        $this->assertSame(
            [],
            $violations,
            "Contracts/ must import only its own namespace, Illuminate\\Contracts\\ or PHP built-ins:\n".implode("\n", $violations)
        );
    }

    public function test_every_module_has_a_contracts_folder(): void
    {
        foreach (SourceScanner::moduleNames() as $module) {
            $this->assertDirectoryExists(
                SourceScanner::basePath("app/Modules/{$module}/Contracts"),
                "Module {$module} is missing its Contracts/ folder."
            );
        }
    }

    private function isAllowed(string $import): bool
    {
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if ($import === $prefix || str_starts_with($import, $prefix)) {
                return true;
            }
        }

        // Unnamespaced built-ins (Throwable, ArrayAccess, ...) never contain a
        // separator; anything namespaced has already been checked above.
        return ! str_contains($import, '\\');
    }
}
