<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Console\Commands;

use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\ChangeStatus;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Lifecycle\TicketLifecycle;
use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Closes resolved tickets the customer has stopped replying to.
 *
 * Goes through the SAME ChangeStatus command a person uses — never a raw
 * UPDATE. That is the whole design: the transition is checked, the version is
 * bumped, the history entry is written, and an auto-close is
 * indistinguishable in shape from a human one. A bulk UPDATE would be faster
 * and would leave a fleet of tickets whose history skips a step.
 */
final class TicketsAutoCloseCommand extends Command
{
    protected $signature = 'tickets:auto-close {--dry-run : List what would close, change nothing} {--limit=500 : Most tickets to close in one run}';

    protected $description = 'Close resolved tickets that the customer has not come back on';

    public function handle(TicketLifecycle $lifecycle, ChangeStatus $changeStatus): int
    {
        /*
         * Guarded rather than assumed. If the code ships before its migration,
         * a scheduled command that threw every fifteen minutes would fill the
         * logs and page somebody; no-oping is the honest behaviour.
         */
        if (! Schema::hasColumn('tickets', 'resolved_at')) {
            Log::warning('tickets.auto_close.skipped', ['reason' => 'lifecycle columns are not migrated yet']);
            $this->warn('Lifecycle columns are missing; skipping.');

            return self::SUCCESS;
        }

        $hours = $lifecycle->autoCloseWindowHours();
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = now()->subHours($hours);

        $candidates = Ticket::query()
            ->where('status', TicketStatus::Resolved->value)
            ->where('resolved_at', '<', $cutoff)
            /*
             * A customer who replied recently has NOT gone quiet, even if the
             * resolution is old. Null means they never said anything at all,
             * which is the commonest case and must not exclude the ticket.
             */
            ->where(function ($query) use ($cutoff): void {
                $query->whereNull('last_customer_activity_at')
                    ->orWhere('last_customer_activity_at', '<', $cutoff);
            })
            // Oldest first, so a backlog drains in the order it aged.
            ->orderBy('resolved_at')
            ->limit($limit)
            ->get(['id', 'reference', 'version']);

        $closed = 0;
        $skipped = 0;

        foreach ($candidates as $ticket) {
            if ($dryRun) {
                $this->line("would close {$ticket->reference}");
                $closed++;

                continue;
            }

            try {
                $changeStatus->handle(
                    // A reason, not a name. "The system closed it" explains
                    // nothing; this tells a reader which job to look at.
                    Actor::system('auto_close'),
                    (string) $ticket->getKey(),
                    // No version: the sweep is exempt from the stale check.
                    null,
                    TicketStatus::Closed,
                );

                $closed++;

                Log::info('tickets.auto_close.closed', [
                    'ticket_id' => (string) $ticket->getKey(),
                    'reference' => $ticket->reference,
                    'window_hours' => $hours,
                ]);
            } catch (Throwable $e) {
                /*
                 * Logged and stepped over, never fatal. The common cause is a
                 * customer reopening the ticket between the SELECT and this
                 * line — the transition check then refuses, correctly. One
                 * such ticket must not stop the other four hundred.
                 */
                $skipped++;

                Log::info('tickets.auto_close.skipped', [
                    'ticket_id' => (string) $ticket->getKey(),
                    'reference' => $ticket->reference,
                    'reason' => $e->getMessage(),
                ]);
            }
        }

        Log::info('tickets.auto_close.finished', [
            'window_hours' => $hours,
            'considered' => $candidates->count(),
            'closed' => $closed,
            'skipped' => $skipped,
            'dry_run' => $dryRun,
        ]);

        $this->info(sprintf(
            '%s %d of %d resolved ticket(s) idle for %d hours (%d skipped).',
            $dryRun ? 'Would close' : 'Closed',
            $closed,
            $candidates->count(),
            $hours,
            $skipped,
        ));

        return self::SUCCESS;
    }
}
