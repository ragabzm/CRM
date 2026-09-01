<?php

declare(strict_types=1);

namespace App\Modules\Email\Domain;

/**
 * Deptrac reasons about classes, not directories: an empty module contributes
 * no nodes to the dependency graph and would silently pass every rule. This
 * placeholder gives the Email layer a node so the architecture tests are
 * actually exercising it.
 *
 * Delete this once the module has real domain code.
 */
final class Placeholder
{
}
