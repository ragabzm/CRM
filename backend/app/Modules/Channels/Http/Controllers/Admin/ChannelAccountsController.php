<?php

declare(strict_types=1);

namespace App\Modules\Channels\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Exceptions\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The list an administrator turns channels on and off from.
 *
 * Deliberately not full CRUD. Accounts are created by the seeder and by the
 * channel stories that add transports; what an administrator does day to day is
 * enable one, disable one, and bind it to the right department. Adding a
 * create-and-delete surface here would invite somebody to delete the account a
 * hundred existing tickets point at.
 */
final class ChannelAccountsController extends Controller
{
    /** How far back the inbound count on the list looks. */
    private const STATS_DAYS = 7;

    /**
     * @response array{data: array<int, array<string, mixed>>}
     */
    public function index(): JsonResponse
    {
        $since = now()->subDays(self::STATS_DAYS);

        /*
         * The counts come back with the list, not from a call per row.
         *
         * Six accounts on screen would otherwise be six more requests, and the
         * number that matters — "is anything actually arriving on this?" — is
         * the one an administrator scans the list for.
         */
        $counts = DB::table('inbound_messages')
            ->where('received_at', '>=', $since)
            ->groupBy('channel_account_id')
            ->selectRaw('channel_account_id, count(*) as total')
            ->pluck('total', 'channel_account_id');

        $rows = DB::table('channel_accounts')
            ->leftJoin('departments', 'departments.id', '=', 'channel_accounts.department_id')
            ->orderBy('channel_accounts.channel')
            ->orderBy('channel_accounts.name')
            ->get([
                'channel_accounts.id',
                'channel_accounts.channel',
                'channel_accounts.name',
                'channel_accounts.is_active',
                'channel_accounts.department_id',
                'departments.name as department_name',
            ]);

        return new JsonResponse([
            'data' => $rows->map(static fn (object $row): array => [
                'id' => (string) $row->id,
                'channel' => $row->channel,
                'name' => $row->name,
                'is_active' => (bool) $row->is_active,
                'department_id' => $row->department_id === null ? null : (int) $row->department_id,
                'department_name' => $row->department_name,
                'inbound_last_7_days' => (int) ($counts[$row->id] ?? 0),
            ])->all(),
        ]);
    }

    /**
     * @response array<string, mixed>
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'department_id' => ['sometimes', 'nullable', 'integer', 'exists:departments,id'],
        ]);

        if (DB::table('channel_accounts')->where('id', $id)->doesntExist()) {
            throw ProblemException::make(
                'channels.account_not_found',
                'Channel account not found',
                404,
                'No channel account with that id.',
            );
        }

        if ($validated === []) {
            throw ProblemException::make(
                'channels.nothing_to_change',
                'Nothing to change',
                422,
                'Send is_active, department_id, or both.',
            );
        }

        DB::table('channel_accounts')->where('id', $id)->update($validated + ['updated_at' => now()]);

        $row = DB::table('channel_accounts')->where('id', $id)->first();

        return new JsonResponse([
            'id' => (string) $row->id,
            'channel' => $row->channel,
            'name' => $row->name,
            'is_active' => (bool) $row->is_active,
            'department_id' => $row->department_id === null ? null : (int) $row->department_id,
        ]);
    }
}
