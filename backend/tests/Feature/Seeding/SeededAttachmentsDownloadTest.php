<?php

declare(strict_types=1);

namespace Tests\Feature\Seeding;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * The seeded attachments can actually be downloaded.
 *
 * A row in `attachments` pointing at bytes that are not there looks identical
 * to a working attachment in every list and every count — right up to the
 * moment somebody clicks it, which is the one moment the demo data existed
 * for. So this walks the whole path: the signed URL, and then the bytes.
 */
final class SeededAttachmentsDownloadTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $this->setUpSpaOrigin();
    }

    public function test_every_seeded_attachment_hands_back_its_bytes(): void
    {
        $admin = User::query()->where('email', 'admin@ragab.test')->firstOrFail();

        $attachments = DB::table('attachments')->get();

        $this->assertCount(2, $attachments);

        foreach ($attachments as $attachment) {
            /*
             * A 302 to a short-lived signed URL, not the bytes. The redirect
             * is the design: the file lives on a private disk and the only way
             * to read it is a link that expires.
             */
            $url = $this->actingAs($admin)
                ->get("/api/v1/attachments/{$attachment->id}/download")
                ->assertRedirect()
                ->headers->get('Location');

            $this->assertIsString($url);

            // The signed URL is absolute; the test client wants the path.
            $path = (string) parse_url($url, PHP_URL_PATH);
            $query = (string) parse_url($url, PHP_URL_QUERY);

            $response = $this->actingAs($admin)->get($path.($query === '' ? '' : '?'.$query));

            $response->assertOk();

            $bytes = $response->streamedContent();

            $this->assertSame(
                (int) $attachment->byte_size,
                strlen($bytes),
                "{$attachment->filename} downloaded a different number of bytes than the row claims.",
            );

            // Not just non-empty: the right KIND of file. A zero-length or
            // truncated fixture would still pass a size check against a row
            // written from the same broken file.
            $this->assertStringStartsWith(
                $attachment->mime_type === 'application/pdf' ? '%PDF' : "\x89PNG",
                $bytes,
            );
        }
    }
}
