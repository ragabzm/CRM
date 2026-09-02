<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Contracts;

/**
 * How many tickets still reference a category.
 *
 * An interface rather than a concrete call so the delete-refusal path can be
 * exercised against a non-zero count today. The tickets table arrives in Story
 * 5.x; until then the real implementation honestly answers zero, and a test
 * double answers seven — which is the only way to prove the refusal works
 * before there is anything to refuse.
 */
interface CategoryUsageProbe
{
    public function activeTicketCount(int $categoryId): int;
}
