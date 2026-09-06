<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes what happened, with the secrets already gone.
 *
 * The redaction is applied HERE rather than by the caller, so there is no way
 * to write a row that skipped it — a logger with a "raw" mode is a logger
 * somebody uses at 3am to debug an integration and forgets to take out.
 */
final class ExchangeLog
{
    public const OUTBOUND = 'outbound';

    public const INBOUND = 'inbound';

    public const QUEUED = 'queued';

    public const SUCCEEDED = 'succeeded';

    public const FAILED = 'failed';

    /**
     * Retries exhausted. Nobody is coming back for this one.
     *
     * A separate status from `failed` because they answer different questions.
     * Four `failed` rows tell an administrator that four attempts did not
     * work; they do not say whether a fifth is on its way. `abandoned` is the
     * row that says the queue has given up and this now needs a person —
     * without it, "it retried and stopped" and "it is still retrying" look
     * identical in the log.
     */
    public const ABANDONED = 'abandoned';

    public function __construct(private readonly Redactor $redactor) {}

    /**
     * Records that a call is about to be made.
     *
     * Written BEFORE the attempt, so an exchange that hangs or crashes the
     * worker still leaves evidence that it was tried. A log written only on
     * completion is silent about precisely the failures that need explaining.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $body
     * @param  array<string, mixed>  $context  What only this integration's rows need.
     */
    public function queued(
        string $integration,
        string $target,
        array $headers,
        array $body,
        int $attempt = 1,
        array $context = [],
        string $direction = self::OUTBOUND,
    ): string {
        $id = (string) Str::ulid();

        DB::table('integration_exchanges')->insert([
            'id' => $id,
            'direction' => $direction,
            'integration' => $integration,
            'target' => $this->redactor->text($target),
            'status' => self::QUEUED,
            'attempt' => $attempt,
            /*
             * NULL when there was nothing to record, rather than an empty
             * envelope. An integration that sends no headers and no body —
             * mail, which deliberately logs neither — should leave the column
             * empty, so "this row carries a payload" stays a question the
             * column itself answers.
             */
            'request' => $headers === [] && $body === [] ? null : $this->encode([
                'headers' => $this->redactor->headers($headers),
                'body' => $this->redactor->body($body),
            ]),
            'context' => $context === [] ? null : $this->encode($this->redactor->body($context)),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function succeeded(string $id, ?int $status, array $body, int $durationMs): void
    {
        $this->update($id, [
            'status' => self::SUCCEEDED,
            'response_status' => $status,
            // Empty stays empty; see `queued`.
            'response' => $body === [] ? null : $this->encode($this->redactor->body($body)),
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * @param  array<string, mixed>  $contextPatch
     */
    public function failed(string $id, ?int $status, string $error, int $durationMs, array $contextPatch = []): void
    {
        if ($contextPatch !== []) {
            /*
             * Read-modify-write, on the failure path only. What the caller
             * learns at failure — a provider's own error code, say — is worth
             * one extra query, and failures are the rare case by construction.
             */
            $existing = DB::table('integration_exchanges')->where('id', $id)->value('context');
            $decoded = is_string($existing) ? json_decode($existing, true) : [];

            $contextPatch = [...(is_array($decoded) ? $decoded : []), ...$contextPatch];
        }

        $this->update($id, [
            ...($contextPatch === [] ? [] : ['context' => $this->encode($this->redactor->body($contextPatch))]),
            'status' => self::FAILED,
            'response_status' => $status,
            /*
             * The error text through the redactor too. A client library that
             * could not connect will happily put the whole request — headers
             * included — into the message it throws, and that is the single
             * likeliest way a credential reaches this table.
             */
            'error' => mb_substr($this->redactor->text($error), 0, 2000),
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function update(string $id, array $attributes): void
    {
        DB::table('integration_exchanges')
            ->where('id', $id)
            ->update([...$attributes, 'updated_at' => now()]);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function encode(array $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * The queue has given up.
     *
     * Written as its own row rather than by amending the last attempt, so the
     * history of what was tried stays intact — an administrator asking "how
     * many times did it try?" should be able to count.
     */
    public function abandoned(string $integration, string $target, string $reason, int $attempts): void
    {
        DB::table('integration_exchanges')->insert([
            'id' => (string) Str::ulid(),
            'direction' => self::OUTBOUND,
            'integration' => $integration,
            'target' => $this->redactor->text($target),
            'status' => self::ABANDONED,
            'attempt' => $attempts,
            'error' => mb_substr($this->redactor->text($reason), 0, 2000),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * One finished exchange, written in a single row.
     *
     * For the exchanges that have no before-and-after: an imported record that
     * is refused on inspection never becomes a call, so there is no `queued`
     * row to update — but the REASON still has to reach the log, because
     * "last night's sync did nothing" is not an answer.
     *
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $integration,
        string $target,
        string $status,
        string $direction = self::INBOUND,
        ?string $error = null,
        array $context = [],
    ): string {
        $id = (string) Str::ulid();

        DB::table('integration_exchanges')->insert([
            'id' => $id,
            'direction' => $direction,
            'integration' => $integration,
            'target' => $this->redactor->text($target),
            'status' => $status,
            'attempt' => 1,
            'error' => $error === null ? null : mb_substr($this->redactor->text($error), 0, 2000),
            'context' => $context === [] ? null : $this->encode($this->redactor->body($context)),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
