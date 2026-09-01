<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support\Settings;

/**
 * The shape a setting's value takes.
 *
 * Stored on the row as well as declared in the registry: the column records
 * what was written, the definition records what is allowed. When the two
 * disagree — because a definition changed after a value was stored — the
 * definition wins and the stored value is rejected on read rather than
 * silently coerced.
 */
enum SettingType: string
{
    case Bool = 'bool';
    case Int = 'int';
    case String = 'string';
    case Json = 'json';

    /**
     * Seconds. A distinct type from Int because the console renders it as a
     * duration ("4 hours") rather than a number, and because a target measured
     * in the wrong unit is the classic SLA bug.
     */
    case DurationSeconds = 'duration_seconds';

    case Enum = 'enum';
}
