<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support\Settings;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Database access for settings. No business rules live here.
 *
 * Values are stored as JSON regardless of type, so one column round-trips a
 * bool, an int and a nested array without a per-type column or a string that
 * has to be parsed back by guesswork.
 *
 * A SECRET is stored encrypted, in a self-describing envelope. Self-describing
 * because `all()` and `find()` have no definition to consult — they read rows,
 * not settings — and a scheme where the reader must already know which keys
 * are secret decrypts the wrong thing the first time a key is renamed.
 *
 * Encryption is at rest only. It protects a database dump, a replica and a
 * backup tape; it is not access control, which is what the capability on the
 * route is for.
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
    public function upsert(string $key, SettingType $type, mixed $value, ?int $actorUserId, bool $secret = false): void
    {
        $now = Carbon::now();

        DB::table(self::TABLE)->upsert(
            [[
                'key' => $key,
                'type' => $type->value,
                'value' => $secret ? $this->seal($value) : json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
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

    /**
     * The envelope. One key, so `decode` can recognise it without being told.
     */
    private const SEALED = '__encrypted';

    private function seal(mixed $value): string
    {
        return (string) json_encode(
            [self::SEALED => Crypt::encryptString((string) json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))],
            JSON_THROW_ON_ERROR,
        );
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
            $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

            if (is_array($decoded) && array_keys($decoded) === [self::SEALED] && is_string($decoded[self::SEALED])) {
                return json_decode(
                    Crypt::decryptString($decoded[self::SEALED]),
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
            }

            /*
             * A plaintext row is still readable. Rows written before secrets
             * were sealed decode here and are re-sealed the next time they are
             * written — the alternative is a deployment where every existing
             * credential reads back as null.
             */
            return $decoded;
        } catch (DecryptException|\JsonException) {
            // A row that cannot be decoded — corrupt JSON, or a secret sealed
            // under a key this deployment no longer has — is treated as absent
            // so one bad value cannot take down every read.
            return null;
        }
    }
}
