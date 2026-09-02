<?php

declare(strict_types=1);

namespace Tests\Feature\Platform\Attachments;

use App\Modules\Platform\Attachments\Jobs\ScanAttachmentJob;
use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

final class AttachmentUploadTest extends TestCase
{
    use InteractsWithAttachments;
    use InteractsWithSpaSession;
    use RefreshDatabase;

    private string $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAttachments();
        $this->owner = $this->ownerId();
        Queue::fake();
    }

    private function upload(UploadedFile $file, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->withIdempotencyKey()->post('/api/v1/attachments', [
            'owner_type' => 'customer',
            'owner_id' => $this->owner,
            'file' => $file,
            ...$overrides,
        ], ['Accept' => 'application/json']);
    }

    public function test_an_accepted_file_lands_in_quarantine_and_is_not_downloadable(): void
    {
        $response = $this->upload($this->pngFile())->assertStatus(201);

        // 201 with downloadable false: the upload really happened, and whether
        // the file is safe is a separate question with its own state.
        $response->assertJsonPath('scan_status', 'pending')
            ->assertJsonPath('downloadable', false);

        $id = $response->json('id');

        // The stored object is named for the row. If these two ever diverge the
        // row points at nothing and the file belongs to nobody.
        Storage::disk('attachments')->assertExists("quarantine/{$id}");
        Storage::disk('attachments')->assertMissing("clean/{$id}");
    }

    public function test_the_scan_is_queued_rather_than_run_in_the_request(): void
    {
        $id = $this->upload($this->pngFile())->assertStatus(201)->json('id');

        // Blocking the response on a scan would tie every upload to a daemon's
        // availability.
        Queue::assertPushed(ScanAttachmentJob::class, fn (ScanAttachmentJob $job): bool => $job->attachmentId === $id);
    }

    public function test_it_records_what_the_file_actually_is_not_what_it_claimed(): void
    {
        $this->upload($this->pngFile('holiday.jpg'))->assertStatus(201);

        // The name says jpg; the contents are a png. The sniffed value is what
        // gets stored, because the other one is a claim.
        $this->assertDatabaseHas('attachments', ['filename' => 'holiday.jpg', 'mime_type' => 'image/png']);
    }

    public function test_it_never_publishes_where_the_bytes_live(): void
    {
        $body = $this->upload($this->pngFile())->assertStatus(201)->json();

        // Publishing the storage path invites someone to try it.
        $this->assertArrayNotHasKey('stored_path', $body);
    }

    public function test_a_unicode_filename_survives_exactly(): void
    {
        $this->upload($this->pngFile('فاتورة-٢٠٢٦.png'))->assertStatus(201);

        $this->assertDatabaseHas('attachments', ['filename' => 'فاتورة-٢٠٢٦.png']);
    }

    public function test_html_disguised_as_an_image_is_refused(): void
    {
        $response = $this->upload($this->htmlFile())->assertStatus(422);

        // The single most important check here: a client-supplied MIME type is
        // a claim typed by whoever is uploading.
        $this->assertContains($response->json('code'), [
            'platform.attachment_mime_mismatch',
            'platform.attachment_type_not_allowed',
        ]);

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_a_refused_upload_leaves_no_bytes_behind(): void
    {
        $this->upload($this->htmlFile())->assertStatus(422);

        // A rejection that still wrote the file is a UI opinion, not a rule.
        $this->assertSame([], Storage::disk('attachments')->allFiles());
    }

    public function test_a_disallowed_type_is_refused_with_the_list(): void
    {
        $registry = $this->app->make(SettingsRegistry::class);
        $registry->set('platform.attachments.allowed_mime_types', ['application/pdf'], null);

        $response = $this->upload($this->pngFile())->assertStatus(422);

        $response->assertJsonPath('code', 'platform.attachment_type_not_allowed');
        // Names what IS accepted, so the reader can act on the refusal.
        $this->assertSame(['application/pdf'], $response->json('allowed_mime_types'));
    }

    public function test_the_allow_list_is_read_at_upload_time_not_at_boot(): void
    {
        // Accepted under the default list...
        $this->upload($this->pngFile())->assertStatus(201);

        $this->app->make(SettingsRegistry::class)
            ->set('platform.attachments.allowed_mime_types', ['application/pdf'], null);

        // ...and refused a moment later, with no deploy in between. That is the
        // entire reason these are settings rather than constants.
        $this->upload($this->pngFile())->assertStatus(422);
    }

    public function test_an_oversize_file_is_refused_with_both_numbers(): void
    {
        $this->app->make(SettingsRegistry::class)
            ->set('platform.attachments.max_bytes', 1024, null);

        $response = $this->upload(UploadedFile::fake()->create('big.png', 50))->assertStatus(422);

        $response->assertJsonPath('code', 'platform.attachment_too_large')
            ->assertJsonPath('max_bytes', 1024);

        // The message says how big it was AND what the limit is, so the reader
        // knows how much to cut.
        $this->assertStringContainsString('The limit is', (string) $response->json('detail'));
    }

    public function test_the_size_cap_is_also_read_at_upload_time(): void
    {
        // Ten bytes: below even a 4x4 PNG, so the cap is what refuses it.
        $this->app->make(SettingsRegistry::class)->set('platform.attachments.max_bytes', 10, null);
        $this->upload($this->pngFile())->assertStatus(422);

        $this->app->make(SettingsRegistry::class)->set('platform.attachments.max_bytes', 10485760, null);
        $this->upload($this->pngFile())->assertStatus(201);
    }

    public function test_an_unknown_owner_kind_is_refused(): void
    {
        $this->upload($this->pngFile(), ['owner_type' => 'invoice'])->assertStatus(422);
    }

    public function test_the_owner_id_must_at_least_be_the_right_shape(): void
    {
        $this->upload($this->pngFile(), ['owner_id' => 'not-a-ulid'])->assertStatus(422);
    }

    public function test_all_three_owner_kinds_are_accepted(): void
    {
        foreach (['customer', 'ticket', 'message'] as $kind) {
            $this->upload($this->pngFile(), ['owner_type' => $kind, 'owner_id' => $this->ownerId()])
                ->assertStatus(201);
        }

        // Supported at the model and API level now, so the ticket and message
        // stories can consume them without a schema change.
        $this->assertDatabaseCount('attachments', 3);
    }

    public function test_a_replayed_upload_does_not_store_the_file_twice(): void
    {
        $key = (string) Str::ulid();

        $first = $this->withHeader('Idempotency-Key', $key)->post('/api/v1/attachments', [
            'owner_type' => 'customer', 'owner_id' => $this->owner, 'file' => $this->pngFile(),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $this->withHeader('Idempotency-Key', $key)->post('/api/v1/attachments', [
            'owner_type' => 'customer', 'owner_id' => $this->owner, 'file' => $this->pngFile(),
        ], ['Accept' => 'application/json']);

        $this->assertDatabaseCount('attachments', 1);
        $this->assertCount(1, Storage::disk('attachments')->allFiles());
        $this->assertNotNull($first->json('id'));
    }

    public function test_a_write_without_an_idempotency_key_is_refused(): void
    {
        $this->post('/api/v1/attachments', [
            'owner_type' => 'customer', 'owner_id' => $this->owner, 'file' => $this->pngFile(),
        ], ['Accept' => 'application/json'])->assertStatus(400);
    }

    public function test_a_guest_cannot_upload(): void
    {
        $this->app['auth']->forgetGuards();
        $this->refreshApplication();
        $this->setUpSpaOrigin();
        Storage::fake('attachments');

        $this->withIdempotencyKey()->post('/api/v1/attachments', [
            'owner_type' => 'customer', 'owner_id' => $this->owner, 'file' => $this->pngFile(),
        ], ['Accept' => 'application/json'])->assertStatus(401);
    }

    public function test_attachments_are_listed_for_their_owner_only(): void
    {
        $other = $this->ownerId();

        $this->upload($this->pngFile('mine.png'))->assertStatus(201);
        $this->upload($this->pngFile('theirs.png'), ['owner_id' => $other])->assertStatus(201);

        $body = $this->getJson("/api/v1/attachments?owner_type=customer&owner_id={$this->owner}")
            ->assertOk()->json();

        $this->assertCount(1, $body['data']);
        $this->assertSame('mine.png', $body['data'][0]['filename']);
    }
}
