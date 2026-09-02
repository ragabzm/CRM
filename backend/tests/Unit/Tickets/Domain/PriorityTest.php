<?php

declare(strict_types=1);

namespace Tests\Unit\Tickets\Domain;

use App\Modules\Tickets\Domain\Priority;
use PHPUnit\Framework\TestCase;

final class PriorityTest extends TestCase
{
    public function test_the_four_priorities_in_severity_order(): void
    {
        // The ORDER is part of the contract: the console renders it and SLA
        // matrices are keyed by it, so a reordering is a behaviour change.
        $this->assertSame(['low', 'normal', 'high', 'urgent'], Priority::values());
    }

    public function test_there_are_exactly_four(): void
    {
        // A fifth priority would have no SLA target and no sort position.
        $this->assertCount(4, Priority::cases());
    }

    public function test_each_case_is_backed_by_its_lowercase_name(): void
    {
        foreach (Priority::cases() as $case) {
            $this->assertSame(strtolower($case->name), $case->value);
        }
    }

    public function test_values_round_trip(): void
    {
        foreach (Priority::values() as $value) {
            $this->assertSame($value, Priority::from($value)->value);
        }
    }
}
