<?php

declare(strict_types=1);

namespace Tests\Feature\Tickets;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * The list ships the labels for the ids it ships.
 *
 * A ticket carries `assignee_id` and `category_id`. For a long time the list
 * returned nothing else, and both columns rendered a dash on every row — an
 * assigned ticket looked exactly like an unclaimed one on the busiest screen
 * in the product.
 *
 * It survived because nothing pointed it out. The tests all built tickets with
 * no assignee and no category, and the only data anyone ran the interface
 * against was typed by hand in the same shape. It took a seeder that fills
 * both columns for anyone to look at the screen and see the dashes.
 *
 * The client cannot fix this for itself: `/users` and `/admin/categories` sit
 * behind capabilities an ordinary agent does not hold.
 */
final class TicketListCarriesTheNamesItNeedsTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $this->setUpSpaOrigin();
    }

    public function test_an_assigned_ticket_comes_with_the_name_of_whoever_holds_it(): void
    {
        $body = $this->actingAs($this->agent())
            ->getJson('/api/v1/tickets?per_page=50')
            ->assertOk()
            ->json();

        $assignees = $body['included']['assignees'] ?? [];

        $this->assertNotEmpty($assignees, 'The list ships assignee ids with no way to render them.');

        foreach ($body['data'] as $ticket) {
            if ($ticket['assignee_id'] === null) {
                continue;
            }

            $this->assertArrayHasKey(
                (string) $ticket['assignee_id'],
                $assignees,
                "Ticket {$ticket['reference']} is assigned to somebody the response never names.",
            );
        }
    }

    public function test_a_categorised_ticket_comes_with_the_name_of_its_category(): void
    {
        $body = $this->actingAs($this->agent())
            ->getJson('/api/v1/tickets?per_page=50')
            ->assertOk()
            ->json();

        $categories = $body['included']['categories'] ?? [];

        $this->assertNotEmpty($categories);

        foreach ($body['data'] as $ticket) {
            if ($ticket['category_id'] === null) {
                continue;
            }

            $this->assertArrayHasKey((string) $ticket['category_id'], $categories);
        }
    }

    public function test_the_category_name_is_in_the_readers_language(): void
    {
        $reader = $this->agent();
        $reader->forceFill(['preferred_locale' => 'ar'])->save();

        $categories = $this->actingAs($reader)
            ->getJson('/api/v1/tickets?per_page=50')
            ->assertOk()
            ->json('included.categories');

        // An Arabic reader shown "Billing" has been handed the column
        // untranslated, which is the failure this is here to catch.
        $this->assertContains('الفوترة', array_values($categories));
    }

    public function test_it_names_only_the_people_on_this_page(): void
    {
        $body = $this->actingAs($this->agent())
            ->getJson('/api/v1/tickets?per_page=1')
            ->assertOk()
            ->json();

        // Not a directory dump. Handing every list request the whole staff
        // table would leak the shape of the organisation to anyone who can
        // read one ticket.
        $this->assertLessThanOrEqual(1, count($body['included']['assignees'] ?? []));
    }

    private function agent(): \App\Models\User
    {
        return \App\Models\User::query()->where('email', 'admin@ragab.test')->firstOrFail();
    }
}
