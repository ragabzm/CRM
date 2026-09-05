<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Channels\Adapters\WebFormChannelAdapter;
use App\Modules\Security\Domain\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The two ways in that exist today, and the department they fall back to.
 *
 * Without this, `channels:doctor` fails on a freshly seeded environment and the
 * public form refuses every submission as "switched off" — which looks exactly
 * like a bug and is really an empty table.
 *
 * It deliberately does NOT write `channels.default_department_id`.
 *
 * AD-5: a seeded settings row shadows the registry default for every developer,
 * invisibly, and makes a fresh environment differ from a real one in a way
 * nothing reports. Both accounts here are bound to a department instead, so the
 * resolver stops at the `channel` rule and the default is never reached.
 * `channels:doctor` knows that and only insists on a default when some active
 * account has no department of its own.
 */
final class DemoChannelsSeeder extends Seeder
{
    /**
     * @var list<array{channel: string, name: string, department: string}>
     */
    private const ACCOUNTS = [
        ['channel' => 'email', 'name' => 'Support mailbox', 'department' => 'Support'],
        ['channel' => WebFormChannelAdapter::CHANNEL, 'name' => 'Public form', 'department' => 'Support'],
    ];

    public function run(): void
    {
        if (! DemoEnvironment::allows($this->command, self::class)) {
            return;
        }

        DemoEnvironment::needs($this, DemoDepartmentsSeeder::class, static fn (): bool => Department::query()->exists());

        $departments = Department::query()->pluck('id', 'name');

        foreach (self::ACCOUNTS as $account) {
            $departmentId = $departments[$account['department']] ?? null;

            /*
             * Matched on channel AND name, which is also the unique index. A
             * second run updates the binding and does not mint a second
             * mailbox — the tickets already pointing at the first one would be
             * orphaned from their provenance.
             */
            $existing = DB::table('channel_accounts')
                ->where('channel', $account['channel'])
                ->where('name', $account['name'])
                ->first();

            if ($existing !== null) {
                DB::table('channel_accounts')->where('id', $existing->id)->update([
                    'is_active' => true,
                    'department_id' => $departmentId,
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('channel_accounts')->insert([
                'id' => (string) Str::ulid(),
                'channel' => $account['channel'],
                'name' => $account['name'],
                'is_active' => true,
                'department_id' => $departmentId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command?->info('Seeded '.count(self::ACCOUNTS).' channel accounts, each bound to a department.');
    }
}
