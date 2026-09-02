<?php

declare(strict_types=1);

namespace Tests\Feature\Platform\Admin;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

final class QuickRepliesApiTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpSpaOrigin();
        $this->seed(RolesAndPermissionsSeeder::class);

        $administrator = User::factory()->create();
        $administrator->syncRoles([Roles::ADMINISTRATOR]);
        $this->actingAs($administrator->refresh());
    }

    /** @return array{en:string,ar:string} */
    private function bilingual(string $english, string $arabic): array
    {
        return ['en' => $english, 'ar' => $arabic];
    }

    private function create(string $english, string $arabic = 'رد'): string
    {
        return (string) $this->withIdempotencyKey()->postJson('/api/v1/admin/quick-replies', [
            'label' => $this->bilingual($english, $arabic),
            'body' => $this->bilingual($english.' body', 'نص'),
        ])->assertStatus(201)->json('id');
    }

    public function test_the_list_starts_empty(): void
    {
        $this->getJson('/api/v1/admin/quick-replies')->assertOk()->assertJsonPath('data', []);
    }

    public function test_a_reply_requires_both_languages(): void
    {
        // A reply that exists only in English is a gap an agent finds mid
        // conversation, with a customer waiting.
        $this->withIdempotencyKey()->postJson('/api/v1/admin/quick-replies', [
            'label' => ['en' => 'Greeting'],
            'body' => ['en' => 'Hello'],
        ])->assertStatus(422);

        $this->getJson('/api/v1/admin/quick-replies')->assertJsonPath('data', []);
    }

    public function test_the_id_is_minted_by_the_server(): void
    {
        $id = (string) $this->withIdempotencyKey()->postJson('/api/v1/admin/quick-replies', [
            'id' => 'chosen-by-the-client',
            'label' => $this->bilingual('Greeting', 'تحية'),
            'body' => $this->bilingual('Hello', 'مرحبا'),
        ])->assertStatus(201)->json('id');

        // A client-chosen id could be picked to collide with an existing reply
        // and overwrite it.
        $this->assertNotSame('chosen-by-the-client', $id);
        $this->assertSame(26, strlen($id));
    }

    public function test_a_reply_is_created_edited_and_deleted(): void
    {
        $id = $this->create('Greeting');

        $this->withIdempotencyKey()->patchJson("/api/v1/admin/quick-replies/{$id}", [
            'label' => $this->bilingual('Welcome', 'أهلا'),
            'body' => $this->bilingual('Welcome aboard', 'أهلا بك'),
        ])->assertOk()->assertJsonPath('label.en', 'Welcome');

        // The id survives an edit, so anything referencing it still resolves.
        $this->getJson('/api/v1/admin/quick-replies')
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.label.ar', 'أهلا');

        $this->withIdempotencyKey()->deleteJson("/api/v1/admin/quick-replies/{$id}")
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_editing_a_missing_reply_is_a_404(): void
    {
        $this->withIdempotencyKey()->patchJson('/api/v1/admin/quick-replies/01JUNKJUNKJUNKJUNKJUNKJUNK', [
            'label' => $this->bilingual('Welcome', 'أهلا'),
            'body' => $this->bilingual('Welcome aboard', 'أهلا بك'),
        ])->assertStatus(404)->assertJsonPath('code', 'platform.quick_reply_not_found');
    }

    public function test_reordering_rewrites_the_order(): void
    {
        $first = $this->create('First');
        $second = $this->create('Second');
        $third = $this->create('Third');

        $this->withIdempotencyKey()->postJson('/api/v1/admin/quick-replies/reorder', [
            'order' => [$third, $first, $second],
        ])->assertOk();

        $this->assertSame(
            [$third, $first, $second],
            array_column($this->getJson('/api/v1/admin/quick-replies')->json('data'), 'id'),
        );
    }

    public function test_a_partial_order_is_refused_rather_than_deleting_the_rest(): void
    {
        $first = $this->create('First');
        $second = $this->create('Second');

        $this->withIdempotencyKey()->postJson('/api/v1/admin/quick-replies/reorder', [
            'order' => [$first],
        ])->assertStatus(422)->assertJsonPath('code', 'platform.quick_reply_order_invalid');

        // Nobody asked to delete anything, so nothing was deleted.
        $this->assertSame(
            [$first, $second],
            array_column($this->getJson('/api/v1/admin/quick-replies')->json('data'), 'id'),
        );
    }

    public function test_an_order_naming_an_unknown_id_is_refused(): void
    {
        $first = $this->create('First');

        $this->withIdempotencyKey()->postJson('/api/v1/admin/quick-replies/reorder', [
            'order' => [$first, '01JUNKJUNKJUNKJUNKJUNKJUNK'],
        ])->assertStatus(422);
    }

    public function test_an_order_that_is_not_a_list_of_ids_is_refused(): void
    {
        $this->create('First');

        $this->withIdempotencyKey()->postJson('/api/v1/admin/quick-replies/reorder', [
            'order' => 'first',
        ])->assertStatus(422)->assertJsonPath('code', 'platform.quick_reply_order_invalid');
    }

    public function test_an_agent_cannot_manage_quick_replies(): void
    {
        $agent = User::factory()->create();
        $agent->syncRoles([Roles::AGENT]);
        $this->actingAs($agent->refresh());

        $this->getJson('/api/v1/admin/quick-replies')->assertStatus(403);
        $this->withIdempotencyKey()->postJson('/api/v1/admin/quick-replies', [
            'label' => $this->bilingual('Greeting', 'تحية'),
            'body' => $this->bilingual('Hello', 'مرحبا'),
        ])->assertStatus(403);
    }
}
