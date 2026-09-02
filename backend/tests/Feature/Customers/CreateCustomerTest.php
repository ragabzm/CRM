<?php

declare(strict_types=1);

namespace Tests\Feature\Customers;

use App\Modules\Customers\Domain\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

final class CreateCustomerTest extends TestCase
{
    use InteractsWithCustomers;
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCustomers();
    }

    public function test_a_customer_is_created_with_several_ways_to_reach_them(): void
    {
        $body = $this->createCustomer('Hana Yousef', [
            ['kind' => 'email', 'value' => 'hana@example.test', 'is_primary' => true],
            ['kind' => 'email', 'value' => 'h.yousef@work.test'],
            ['kind' => 'phone', 'value' => '+44 20 7946 0958'],
        ]);

        // Several emails AND several phones. A schema with one `email` column
        // is a schema that will need `email2`.
        $this->assertCount(3, $body['identifiers']);
        $this->assertSame('Hana Yousef', $body['full_name']);
        $this->assertSame('active', $body['state']);
    }

    public function test_the_value_is_stored_exactly_as_it_was_entered(): void
    {
        $body = $this->createCustomer('Hana Yousef', [
            ['kind' => 'phone', 'value' => '+44 20 7946 0958'],
        ]);

        // The normalised form exists to compare with, never to show. Nobody
        // wants their phone number handed back with the punctuation removed.
        $this->assertSame('+44 20 7946 0958', $body['identifiers'][0]['value']);
        $this->assertDatabaseHas('contact_identifiers', ['value_normalised' => '2079460958']);
    }

    public function test_the_reference_is_short_enough_to_read_aloud(): void
    {
        $reference = $this->createCustomer()['reference'];

        $this->assertMatchesRegularExpression('/^C-['.Customer::REFERENCE_ALPHABET.']{8}$/', $reference);

        // No letters or digits that get misheard on a phone line.
        foreach (['I', 'L', 'O', 'U', '0', '1'] as $ambiguous) {
            $this->assertStringNotContainsString($ambiguous, substr($reference, 2));
        }
    }

    public function test_the_id_does_not_leak_how_many_customers_exist(): void
    {
        $first = $this->createCustomer('First', [['kind' => 'email', 'value' => 'a@example.test']])['id'];
        $second = $this->createCustomer('Second', [['kind' => 'email', 'value' => 'b@example.test']])['id'];

        // ULIDs, not auto-increment: a sequential id in every URL an agent
        // pastes anywhere is business information nobody meant to publish.
        $this->assertTrue(Str::isUlid($first));
        $this->assertTrue(Str::isUlid($second));
        $this->assertNotSame($first, $second);

        // And genuinely not a counter — "1" and "2" would both be 26 characters
        // if someone padded them, so the shape is what is asserted.
        $this->assertDoesNotMatchRegularExpression('/^0*\d{1,3}$/', $first);
    }

    public function test_a_customer_needs_a_way_to_be_reached(): void
    {
        $this->withIdempotencyKey()
            ->postJson('/api/v1/customers', [
                'full_name' => 'Nobody',
                'department_id' => $this->departmentId,
                'identifiers' => [],
            ])
            ->assertStatus(422);

        // A record with no contact details is a name in a list, not a customer.
        $this->assertDatabaseCount('customers', 0);
    }

    public function test_a_customer_needs_a_department(): void
    {
        $payload = $this->customerPayload();
        unset($payload['department_id']);

        $this->withIdempotencyKey()->postJson('/api/v1/customers', $payload)->assertStatus(422);
    }

    public function test_a_department_that_does_not_exist_is_refused(): void
    {
        $this->withIdempotencyKey()
            ->postJson('/api/v1/customers', $this->customerPayload(extra: ['department_id' => 999999]))
            ->assertStatus(422);
    }

    public function test_a_preferred_channel_must_be_one_the_customer_actually_has(): void
    {
        $response = $this->withIdempotencyKey()->postJson('/api/v1/customers', $this->customerPayload(
            identifiers: [['kind' => 'phone', 'value' => '555 123 4567']],
            extra: ['preferred_channel' => 'email'],
        ))->assertStatus(422);

        // "Prefers email" on a record holding only a phone number is a promise
        // the product cannot keep — the first automated reply goes nowhere.
        $this->assertStringContainsString('email', implode(' ', (array) $response->json('errors.preferred_channel')));
    }

    public function test_a_preferred_channel_the_customer_has_is_accepted(): void
    {
        $body = $this->createCustomer('Hana', [
            ['kind' => 'phone', 'value' => '555 123 4567'],
        ], ['preferred_channel' => 'phone']);

        $this->assertSame('phone', $body['preferred_channel']);
    }

    public function test_the_same_detail_cannot_be_listed_twice_on_one_customer(): void
    {
        $response = $this->withIdempotencyKey()->postJson('/api/v1/customers', $this->customerPayload(
            identifiers: [
                ['kind' => 'phone', 'value' => '+44 20 7946 0958'],
                ['kind' => 'phone', 'value' => '020 7946 0958'],
            ],
        ))->assertStatus(422);

        // Caught by the application so the message can name WHICH row; the
        // unique index behind it would surface as an unhelpful 500.
        $this->assertSame('customers.identifier_duplicated', $response->json('code'));
        $this->assertSame('identifiers.1.value', $response->json('pointer'));
    }

    public function test_a_value_nobody_could_be_contacted_on_is_refused(): void
    {
        $this->withIdempotencyKey()->postJson('/api/v1/customers', $this->customerPayload(
            identifiers: [['kind' => 'phone', 'value' => 'call the office']],
        ))->assertStatus(422)->assertJsonPath('code', 'customers.identifier_invalid');
    }

    public function test_a_replayed_request_does_not_create_a_second_customer(): void
    {
        $key = (string) Str::ulid();
        $payload = $this->customerPayload();

        $first = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/customers', $payload)->assertStatus(201);
        $second = $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/customers', $payload);

        // A retried create — a flaky connection, a double-clicked button —
        // must not produce two people.
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, DB::table('customers')->count());
    }

    public function test_a_created_customer_is_recorded_in_the_audit_log(): void
    {
        $this->createCustomer();

        $this->assertDatabaseHas('audit_entries', [
            'action' => 'customer.field_changed',
            'target_type' => 'customer',
        ]);
    }

    public function test_every_id_the_product_mints_has_one_casing(): void
    {
        $body = $this->createCustomer();

        // Two casings in one datastore is a trap for anyone comparing ids by
        // hand. HasUlids defaults to lowercase; everything here is canonical
        // uppercase Crockford.
        $this->assertSame(strtoupper($body['id']), $body['id']);
        $this->assertSame(strtoupper($body['identifiers'][0]['id']), $body['identifiers'][0]['id']);
    }
}
