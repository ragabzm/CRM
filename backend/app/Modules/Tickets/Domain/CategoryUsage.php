<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain;

use App\Modules\Tickets\Contracts\CategoryUsageProbe;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How many tickets still reference a category.
 *
 * The enforcement path for a rule-blocked delete exists NOW even though the
 * answer is always zero: the tickets table arrives in Story 5.x. Building the
 * refusal later would mean shipping a delete that silently strands work in the
 * meantime, and retrofitting the count into a UI that never had one.
 */
final class CategoryUsage implements CategoryUsageProbe
{
    /** Statuses that still need someone to act. */
    public const ACTIVE_STATUSES = ['open', 'pending'];

    public function activeTicketCount(int $categoryId): int
    {
        // TODO(Story 5.x): drop the guard once tickets carry a category.
        if (! Schema::hasTable('tickets') || ! Schema::hasColumn('tickets', 'category_id')) {
            return 0;
        }

        return DB::table('tickets')
            ->where('category_id', $categoryId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->count();
    }
}
