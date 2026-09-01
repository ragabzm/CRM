<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Platform\Support\RequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes write endpoints safe to retry.
 *
 * First call for a key reserves a row, runs the handler and stores the outcome.
 * A repeat with the same request fingerprint replays the stored response instead
 * of acting twice; a repeat with a different fingerprint is a caller bug and is
 * rejected with 409 rather than silently replayed.
 */
final class IdempotencyKey
{
    public const HEADER = 'Idempotency-Key';

    public const TABLE = 'idempotency_keys';

    /** Rows older than this are prunable and a repeat key is treated as fresh. */
    public const TTL_HOURS = 24;

    private const METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    private const STATUS_IN_FLIGHT = 'in_flight';

    private const STATUS_COMPLETED = 'completed';

    /** A ULID (26 Crockford base32) or a canonical UUID. */
    private const KEY_PATTERN = '/^(?:[0-7][0-9A-HJKMNP-TV-Z]{25}|[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12})$/';

    /**
     * Headers that describe *this* hop rather than the stored result. Replaying
     * them would pin a stale correlation id and a stale clock onto the retry, so
     * they are dropped on store and re-stamped by the surrounding middleware.
     */
    private const VOLATILE_HEADERS = ['date', 'x-request-id', 'set-cookie'];

    /** Loser-of-the-race poll settings: 100 ms steps, 3 s ceiling. */
    private const POLL_INTERVAL_MICROSECONDS = 100_000;

    private const POLL_MAX_ATTEMPTS = 30;

    public function __construct(
        private readonly RequestContext $context,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->getMethod(), self::METHODS, true)) {
            return $next($request);
        }

        $key = $this->requireKey($request);
        $fingerprint = $this->fingerprint($request);

        $this->pruneExpired($key);

        [$actorType, $actorId] = $this->context->actor();

        $reserved = DB::table(self::TABLE)->insertOrIgnore([
            'key' => $key,
            'actor_type' => $actorType,
            'actor_id' => $actorId === null ? null : (string) $actorId,
            'request_fingerprint' => $fingerprint,
            'status' => self::STATUS_IN_FLIGHT,
            'created_at' => Carbon::now(),
        ]);

        if ($reserved === 0) {
            return $this->replayOrReject($key, $fingerprint);
        }

        try {
            /** @var Response $response */
            $response = $next($request);
        } catch (\Throwable $e) {
            // A failed attempt must not permanently burn the key: release the
            // reservation so the caller's retry can genuinely re-run.
            DB::table(self::TABLE)->where('key', $key)->delete();

            throw $e;
        }

        if ($response->getStatusCode() >= 500) {
            // Server errors are not an outcome worth replaying. Release the key
            // so the caller's retry gets a real second attempt.
            DB::table(self::TABLE)->where('key', $key)->delete();

            return $response;
        }

        $this->store($key, $response);

        return $response;
    }

    private function requireKey(Request $request): string
    {
        $key = $request->headers->get(self::HEADER);

        if (! is_string($key) || $key === '') {
            throw ProblemException::make(
                'platform.validation_failed',
                'Idempotency-Key header is required.',
                400,
                sprintf('%s write requests must carry an %s header containing a ULID or UUID.', $request->getMethod(), self::HEADER),
            );
        }

        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw ProblemException::make(
                'platform.validation_failed',
                'Idempotency-Key header is malformed.',
                400,
                sprintf('%s must be a ULID (26 characters) or a canonical UUID.', self::HEADER),
            );
        }

        return $key;
    }

    private function fingerprint(Request $request): string
    {
        return hash('sha256', implode("\n", [
            $request->getMethod(),
            $request->getPathInfo(),
            $request->getContent(),
        ]));
    }

    /**
     * Expiry is enforced on read as well as by the nightly prune, so a key that
     * outlives its TTL behaves as fresh even if the scheduler has not run.
     */
    private function pruneExpired(string $key): void
    {
        DB::table(self::TABLE)
            ->where('key', $key)
            ->where('created_at', '<', Carbon::now()->subHours(self::TTL_HOURS))
            ->delete();
    }

    private function replayOrReject(string $key, string $fingerprint): Response
    {
        for ($attempt = 0; $attempt < self::POLL_MAX_ATTEMPTS; $attempt++) {
            $row = DB::table(self::TABLE)->where('key', $key)->first();

            if ($row === null) {
                // The winner failed and released the reservation between our
                // insert attempt and this read. Treat the key as unused.
                throw ProblemException::make(
                    'platform.idempotency_in_flight',
                    'Idempotent request is still being processed.',
                    425,
                    'A concurrent request with this Idempotency-Key did not complete. Retry the request.',
                );
            }

            if (! hash_equals((string) $row->request_fingerprint, $fingerprint)) {
                throw ProblemException::make(
                    'platform.idempotency_conflict',
                    'Idempotency-Key reused with a different request.',
                    409,
                    sprintf('%s has already been used for a request with a different method, path or body.', self::HEADER),
                );
            }

            if ($row->status === self::STATUS_COMPLETED) {
                return $this->replay($row);
            }

            usleep(self::POLL_INTERVAL_MICROSECONDS);
        }

        throw ProblemException::make(
            'platform.idempotency_in_flight',
            'Idempotent request is still being processed.',
            425,
            'A concurrent request with this Idempotency-Key has not finished yet. Retry shortly.',
        );
    }

    private function replay(object $row): Response
    {
        /** @var array<string, list<string>> $headers */
        $headers = json_decode((string) $row->response_headers, true, 512, JSON_THROW_ON_ERROR) ?: [];

        return new Response(
            $this->decodeBody($row->response_body),
            (int) $row->response_status,
            $headers,
        );
    }

    private function store(string $key, Response $response): void
    {
        DB::table(self::TABLE)->where('key', $key)->update([
            'status' => self::STATUS_COMPLETED,
            'response_status' => $response->getStatusCode(),
            'response_headers' => json_encode($this->storableHeaders($response), JSON_THROW_ON_ERROR),
            'response_body' => $this->encodeBody((string) $response->getContent()),
            'completed_at' => Carbon::now(),
        ]);
    }

    /**
     * @return array<string, list<string>>
     */
    private function storableHeaders(Response $response): array
    {
        $headers = [];

        foreach ($response->headers->all() as $name => $values) {
            if (! in_array(strtolower($name), self::VOLATILE_HEADERS, true)) {
                $headers[$name] = $values;
            }
        }

        return $headers;
    }

    /**
     * Bodies are stored in a binary column (bytea on Postgres). Base64 keeps the
     * round trip byte-exact regardless of driver-level binary handling.
     */
    private function encodeBody(string $body): string
    {
        return base64_encode($body);
    }

    private function decodeBody(mixed $stored): string
    {
        if (is_resource($stored)) {
            $stored = stream_get_contents($stored);
        }

        $decoded = base64_decode((string) $stored, true);

        return $decoded === false ? '' : $decoded;
    }
}
