<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Tickets\Domain\Category;
use Illuminate\Database\Seeder;

/**
 * Five categories, each in both languages.
 *
 * Bilingual on purpose rather than as decoration: a category list is the
 * shortest path to finding out that an Arabic string does not survive the
 * round trip through the database, the API and the interface. An English-only
 * seed would look correct right up until the first real deployment.
 */
final class DemoCategoriesSeeder extends Seeder
{
    /** @var list<array{string, string}> English, Arabic — in display order */
    public const CATEGORIES = [
        ['General', 'عام'],
        ['Billing', 'الفوترة'],
        ['Technical', 'دعم فني'],
        ['Account', 'الحساب'],
        ['Feedback', 'ملاحظات'],
    ];

    public function run(): void
    {
        if (! DemoEnvironment::allows($this->command, self::class)) {
            return;
        }

        foreach (self::CATEGORIES as $index => [$english, $arabic]) {
            Category::query()->updateOrCreate(
                ['name_en' => $english],
                ['name_ar' => $arabic, 'sort_order' => $index + 1],
            );
        }

        $this->command?->info('Seeded '.count(self::CATEGORIES).' demo categories (EN + AR).');
    }
}
