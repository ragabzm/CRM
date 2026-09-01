<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support\Settings;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Database access for settings. No business rules live here.
 *
 * Values are stored as JSON regardless of type, so one column round-trips a
 * bool, an int and a nested array without a per-type column or a string that
 * has to be parsed back by guesswork.
 */
final class SettingsRepository
{
    public const TABLE = 'settings';

    /**
     * Every stored row, decoded.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $rows = DB::table(self::TABLE)->get(['key', 'value']);

        $decoded = [];

        foreach ($rows as $row) {
            $decoded[(string) $row->key] = $this->decode($row->value);
        }

        return $decoded;
    }

    public function find(string $key): mixed
    {
        $row = DB::table(self::TABLE)->where('key', $key)->first(['value']);

        return $row === null ? null : $this->decode($row->value);
    }

    public function exists(string $key): bool
    {
        return DB::table(self::TABLE)->where('key', $key)->exists();
    }

    /**
     * Insert or update, atomically.
     *
     * Two administrators writing the same key concurrently is last-write-wins
     * rather than an error: settings are a small set of deliberate values, and
     * an optimistic-locking conflict on "set the attachment cap" would be
     * ceremony without a corresponding risk. The audit entry records both
     * writes, so the sequence is reconstructable.
     */
    public function upsert(string $key, SettingType $type, mixed $value, ?int $actorUserId): void
    {
        $now = Carbon::now();

        DB::table(self::TABLE)->upsert(
            [[
                'key' => $key,
                'type' => $type->value,
                'value' => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'updated_by' => $actorUserId,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['key'],
            ['type', 'value', 'updated_by', 'updated_at'],
        );
    }

    public function forget(string $key): void
    {
        DB::table(self::TABLE)->where('key', $key)->delete();
    }

    private function decode(mixed $raw): mixed
    {
        if ($raw === null) {
            return null;
        }

        if (is_resource($raw)) {
            $raw = stream_get_contents($raw);
        }

        try {
            return json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            // A row that cannot be decoded is treated as absent so one bad value
            // cannot take down every read.
            return null;
        }
    }
}
