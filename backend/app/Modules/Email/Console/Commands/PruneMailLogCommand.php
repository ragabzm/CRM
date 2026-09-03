<?php

declare(strict_types=1);

namespace App\Modules\Email\Console\Commands;

use App\Modules\Email\Domain\MailLogEntry;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Illuminate\Console\Command;

/**
 * Keeps the mail log from becoming the largest table in the database.
 *
 * Every send writes a row; a busy desk writes thousands a day, and the value of
 * a row falls off a cliff after the incident it belonged to is closed. Keeping
 * them forever costs storage, slows the admin view it exists for, and holds a
 * record of who was emailed long after anyone could justify it.
 *
 * How long is a SETTING, because the right answer is a compliance question
 * rather than an engineering one — and it differs between deployments.
 */
final class PruneMailLogCommand extends Command
{
    protected $signature = 'email:prune-log {--dry-run : Report what would be removed and delete nothing}';

    protected $description = 'Delete mail log entries older than the configured retention.';

    public function handle(SettingsRegistry $settings): int
    {
        $days = (int) $settings->get('email.log.retention_days');

        if ($days < 1) {
            $this->error('Retention must be at least a day; refusing to prune.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        $query = MailLogEntry::query()->where('occurred_at', '<', $cutoff);

        if ($this->option('dry-run')) {
            $this->info(sprintf('%d entries older than %s would be removed.', $query->count(), $cutoff->toDateString()));

            return self::SUCCESS;
        }

        /*
         * Chunked, not one statement. A first prune on a table that has been
         * accumulating for a year would otherwise lock it for the length of the
         * delete, and the mail log is written on every send — including while
         * this runs.
         */
        $removed = 0;

        do {
            $deleted = MailLogEntry::query()
                ->where('occurred_at', '<', $cutoff)
                ->limit(1000)
                ->delete();

            $removed += $deleted;
        } while ($deleted > 0);

        $this->info(sprintf('Removed %d mail log entries older than %d days.', $removed, $days));

        return self::SUCCESS;
    }
}
