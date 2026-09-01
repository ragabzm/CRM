<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Modules\Platform\Http\Middleware\IdempotencyKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class IdempotencyKeyTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/healthz-echo';

    public function test_a_write_without_a_key_is_rejected(): void
    {
        $response = $this->postJson(self::ENDPOINT, ['a' => 1]);

        $response->assertStatus(400);
        $this->assertSame('platform.validation_failed', $response->json('code'));
    }

    public function test_a_malformed_key_is_rejected(): void
    {
        $response = $this->postJson(self::ENDPOINT, ['a' => 1], [IdempotencyKey::HEADER => 'not-a-ulid']);

        $response->assertStatus(400);
        $this->assertSame('platform.validation_failed', $response->json('code'));
    }

    public function test_a_uuid_is_accepted_as_well_as_a_ulid(): void
    {
        $this->postJson(self::ENDPOINT, ['a' => 1], [IdempotencyKey::HEADER => (string) Str::uuid()])
            ->assertOk();
    }

    public function test_the_first_call_executes_and_stores_the_response(): void
    {
        $key = (string) Str::ulid();

        $response = $this->postJson(self::ENDPOINT, ['a' => 1], [IdempotencyKey::HEADER => $key]);

        $response->assertOk();
        $this->assertSame(['a' => 1], $response->json('echo'));

        $row = DB::table(IdempotencyKey::TABLE)->where('key', $key)->first();

        $this->assertNotNull($row);
        $this->assertSame('completed', $row->status);
        $this->assertSame(200, (int) $row->response_status);
    }

    public function test_a_repeat_with_the_same_fingerprint_replays_the_stored_response(): void
    {
        $key = (string) Str::ulid();

        $first = $this->postJson(self::ENDPOINT, ['a' => 1], [IdempotencyKey::HEADER => $key]);
        $second = $this->postJson(self::ENDPOINT, ['a' => 1], [IdempotencyKey::HEADER => $key]);

        $second->assertStatus($first->getStatusCode());
        $this->assertSame(
            $first->getContent(),
            $second->getContent(),
            'A replayed response body must be byte-identical to the stored one.'
        );

        // Exactly one reservation: the handler ran once, not twice.
        $this->assertSame(1, DB::table(IdempotencyKey::TABLE)->where('key', $key)->count());
    }

    public function test_a_repeat_with_a_different_fingerprint_is_a_conflict(): void
    {
        $key = (string) Str::ulid();

        $this->postJson(self::ENDPOINT, ['a' => 1], [IdempotencyKey::HEADER => $key])->assertOk();

        $response = $this->postJson(self::ENDPOINT, ['a' => 2], [IdempotencyKey::HEADER => $key]);

        $response->assertStatus(409);
        $this->assertSame('platform.idempotency_conflict', $response->json('code'));
    }

    public function test_a_concurrent_request_that_has_not_finished_yields_425(): void
    {
        $key = (string) Str::ulid();
        $body = ['a' => 1];

        // Simulate the losing side of the race: the winner has reserved the key
        // but not yet stored a response.
        DB::table(IdempotencyKey::TABLE)->insert([
            'key' => $key,
            'actor_type' => 'guest',
            'actor_id' => null,
            'request_fingerprint' => $this->fingerprintFor($body),
            'status' => 'in_flight',
            'created_at' => Carbon::now(),
        ]);

        $response = $this->postJson(self::ENDPOINT, $body, [IdempotencyKey::HEADER => $key]);

        $response->assertStatus(425);
        $this->assertSame('platform.idempotency_in_flight', $response->json('code'));
    }

    public function test_an_expired_key_is_treated_as_fresh(): void
    {
        $key = (string) Str::ulid();

        $this->postJson(self::ENDPOINT, ['a' => 1], [IdempotencyKey::HEADER => $key])->assertOk();

        DB::table(IdempotencyKey::TABLE)->where('key', $key)->update([
            'created_at' => Carbon::now()->subHours(IdempotencyKey::TTL_HOURS + 1),
        ]);

        // Same key, different body: this would be a 409 were the row still live.
        $this->postJson(self::ENDPOINT, ['a' => 2], [IdempotencyKey::HEADER => $key])->assertOk();
    }

    public function test_reads_are_not_subject_to_the_header(): void
    {
        $this->getJson('/api/v1/healthz')->assertOk();
    }

    public function test_the_prune_command_removes_expired_rows_only(): void
    {
        $fresh = (string) Str::ulid();
        $stale = (string) Str::ulid();

        foreach ([$fresh => Carbon::now(), $stale => Carbon::now()->subHours(IdempotencyKey::TTL_HOURS + 1)] as $key => $createdAt) {
            DB::table(IdempotencyKey::TABLE)->insert([
                'key' => $key,
                'actor_type' => 'guest',
                'actor_id' => null,
                'request_fingerprint' => str_repeat('0', 64),
                'status' => 'completed',
                'created_at' => $createdAt,
            ]);
        }

        $this->artisan('platform:prune-idempotency-keys')->assertSuccessful();

        $this->assertSame(1, DB::table(IdempotencyKey::TABLE)->where('key', $fresh)->count());
        $this->assertSame(0, DB::table(IdempotencyKey::TABLE)->where('key', $stale)->count());
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function fingerprintFor(array $body): string
    {
        return hash('sha256', implode("\n", [
            'POST',
            self::ENDPOINT,
            json_encode($body, JSON_THROW_ON_ERROR),
        ]));
    }
}
