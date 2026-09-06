<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Console\Commands;

use App\Modules\Integrations\Domain\ErpSettings;
use App\Modules\Integrations\Domain\ExchangeRetention;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY way a row leaves the exchange log.
 *
 * There is no delete endpoint, no admin button and no ad-hoc cleanup — a log
 * somebody can tidy is a log that gets tidied the morning after the thing
 * worth explaining. Retention is a policy an administrator sets once, and this
 * command is the policy running.
 */
final class PruneExchangeLogCommand extends Command
{
    protected $signature = 'integrations:prune-log';

    protected $description = 'Delete exchange log rows past the configured retention period.';

    public function handle(ErpSettings $settings, ExchangeRetention $retention): int
    {
        $days = $settings->retentionDays();

        $query = DB::table('integration_exchanges')
            ->where('occurred_at', '<', now()->subDays($days));

        if (($claimed = $retention->claimed()) !== []) {
            /*
             * Rows another module sweeps under its own period. Deleting them
             * here would be this integration's retention silently overriding
             * somebody else's compliance answer — and it would look like data
             * loss, not like a policy.
             */
            $query->whereNotIn('integration', $claimed);
        }

        $deleted = $query->delete();

        $this->info($deleted === 0
            ? 'Nothing past retention.'
            : "Pruned {$deleted} exchange(s) older than {$days} days.");

        return self::SUCCESS;
    }
}
