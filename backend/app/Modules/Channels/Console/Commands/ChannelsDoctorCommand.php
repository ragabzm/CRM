<?php

declare(strict_types=1);

namespace App\Modules\Channels\Console\Commands;

use App\Modules\Channels\Domain\Intake\DepartmentResolver;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Is the intake actually able to run?
 *
 * Three questions a deployment should be refused for, asked here rather than
 * thrown at boot. A service provider that throws when the default department is
 * unset cannot be booted to RUN THE MIGRATION that creates the departments —
 * a fresh install would never get off the ground. Refusing the deploy is the
 * same guarantee without the chicken and egg.
 *
 * Exit code 1 on any failure, so CI and a deploy step can gate on it.
 */
final class ChannelsDoctorCommand extends Command
{
    protected $signature = 'channels:doctor';

    protected $description = 'Checks that inbound message intake is configured and can run.';

    public function handle(SettingsRegistry $settings): int
    {
        $problems = [];

        $default = (int) $settings->get(DepartmentResolver::SETTING);

        /*
         * The default is only a problem when it can be REACHED.
         *
         * The resolver stops at the first hit, and an account with its own
         * department never falls through. Insisting on a default that nothing
         * would ever read is the kind of check people learn to ignore, and a
         * check people ignore is worse than no check.
         */
        $unbound = DB::table('channel_accounts')
            ->where('is_active', true)
            ->whereNull('department_id')
            ->pluck('name')
            ->all();

        if ($default <= 0 && $unbound !== []) {
            $problems[] = sprintf(
                '%s has no value, and these active accounts have no department of their own: %s. '
                .'A message arriving on one of them would open a ticket in nobody’s queue.',
                DepartmentResolver::SETTING,
                implode(', ', $unbound),
            );
        } elseif ($default > 0 && DB::table('departments')->where('id', $default)->doesntExist()) {
            $problems[] = sprintf(
                '%s points at department %d, which does not exist.',
                DepartmentResolver::SETTING,
                $default,
            );
        }

        /*
         * The half-applied migration. `mail_inbound` and `inbound_messages`
         * both present means the fold ran partway: two tables answering "have
         * we seen this message?" is how a duplicate gets through.
         */
        if (Schema::hasTable('mail_inbound') && Schema::hasTable('inbound_messages')) {
            $problems[] = 'mail_inbound and inbound_messages both exist. The migration that folds one into '
                .'the other did not finish; intake must not run until one is removed.';
        }

        if (DB::table('channel_accounts')->where('is_active', true)->doesntExist()) {
            $problems[] = 'No channel account is active. Nothing can arrive.';
        }

        if ($problems === []) {
            $this->info('Channels: ready.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->error('  '.$problem);
        }

        return self::FAILURE;
    }
}
