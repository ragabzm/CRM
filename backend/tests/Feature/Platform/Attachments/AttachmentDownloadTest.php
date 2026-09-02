<?php

declare(strict_types=1);

namespace Tests\Feature\Platform\Attachments;

use App\Modules\Platform\Attachments\Application\SafeContentType;
use App\Modules\Platform\Attachments\Domain\Attachment;
use App\Modules\Platform\Attachments\Domain\ScanStatus;
use App\Modules\Platform\Attachments\Infrastructure\StorageSignedUrlIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

final class AttachmentDownloadTest extends TestCase
{
    use InteractsWithAttachments;
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAttachments();
    }

    private function attachment(ScanStatus $status, string $mime = 'image/png', string $filename = 'receipt.png'): Attachment
    {
        $id = (string) Str::ulid();
        $prefix = $status === ScanStatus::Clean ? 'clean' : 'quarantine';

        Storage::disk('attachments')->put("{$prefix}/{$id}", 'bytes');

        $attachment = new Attachment([
            'owner_type' => 'customer',
            'owner_id' => $this->ownerId(),
            'filename' => $filename,
            'stored_path' => "{$prefix}/{$id}",
            'byte_size' => 5,
            'mime_type' => $mime,
            'uploader_id' => '1',
            'uploaded_at' => now(),
            'scan_status' => $status->value,
        ]);
        $attachment->setAttribute('id', $id);
        $attachment->save();

        return $attachment;
    }

    public function test_a_pending_file_cannot_be_downloaded(): void
    {
        $attachment = $this->attachment(ScanStatus::Pending);

        $this->getJson("/api/v1/attachments/{$attachment->getKey()}/download")
            ->assertStatus(403)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('code', 'platform.attachment_not_downloadable')
            ->assertJsonPath('scan_status', 'pending');
    }

    public function test_the_refusal_explains_which_kind_of_wait_this_is(): void
    {
        $pending = $this->attachment(ScanStatus::Pending);
        $failed = $this->attachment(ScanStatus::Failed);

        // "Still being scanned" and "did not pass" are different news, and the
        // reader acts differently on each.
        $this->assertStringContainsString(
            'still being scanned',
            (string) $this->getJson("/api/v1/attachments/{$pending->getKey()}/download")->json('detail'),
        );
        $this->assertStringContainsString(
            'did not pass',
            (string) $this->getJson("/api/v1/attachments/{$failed->getKey()}/download")->json('detail'),
        );
    }

    public function test_a_failed_file_can_never_be_downloaded(): void
    {
        $attachment = $this->attachment(ScanStatus::Failed);

        $this->getJson("/api/v1/attachments/{$attachment->getKey()}/download")->assertStatus(403);
    }

    public function test_a_clean_file_redirects_to_a_link_rather_than_streaming(): void
    {
        $attachment = $this->attachment(ScanStatus::Clean);

        $response = $this->get("/api/v1/attachments/{$attachment->getKey()}/download")
            ->assertStatus(302);

        // Never streams bytes through the application in production: the
        // redirect points at storage, which validates the signature itself.
        $this->assertNotSame('', (string) $response->headers->get('Location'));
    }

    public function test_the_link_expires(): void
    {
        $attachment = $this->attachment(ScanStatus::Clean);

        $location = (string) $this->get("/api/v1/attachments/{$attachment->getKey()}/download")
            ->assertStatus(302)->headers->get('Location');

        // A URL pasted into a chat thread should be dead before anyone else
        // opens it.
        $this->assertStringContainsString('expires=', $location);
        $this->assertStringContainsString('signature=', $location);
    }

    public function test_the_redirect_is_never_cached(): void
    {
        $attachment = $this->attachment(ScanStatus::Clean);

        $response = $this->get("/api/v1/attachments/{$attachment->getKey()}/download");

        // The Location carries a credential; a cached 302 would hand it to the
        // next person on a shared machine.
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_the_file_is_served_as_an_attachment_not_rendered(): void
    {
        $attachment = $this->attachment(ScanStatus::Clean);
        $location = (string) $this->get("/api/v1/attachments/{$attachment->getKey()}/download")
            ->headers->get('Location');

        $response = $this->get($location)->assertOk();

        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    public function test_html_that_passed_the_scan_is_still_never_served_as_html(): void
    {
        // A clean bill of health from a virus scanner says nothing about
        // stored XSS: an HTML file served inline from a trusted origin runs its
        // script in that origin.
        $attachment = $this->attachment(ScanStatus::Clean, 'text/html', 'notes.html');

        $location = (string) $this->get("/api/v1/attachments/{$attachment->getKey()}/download")
            ->headers->get('Location');

        $response = $this->get($location)->assertOk();

        $this->assertStringStartsWith(
            SafeContentType::FALLBACK,
            (string) $response->headers->get('Content-Type'),
        );
    }

    public function test_svg_is_treated_as_dangerous_too(): void
    {
        // An image everywhere except in a browser, where it is a document that
        // can carry <script>.
        $this->assertSame(SafeContentType::FALLBACK, SafeContentType::for('image/svg+xml'));
        $this->assertSame(SafeContentType::FALLBACK, SafeContentType::for('application/javascript'));
        $this->assertSame(SafeContentType::FALLBACK, SafeContentType::for('text/html; charset=utf-8'));
        $this->assertSame(SafeContentType::FALLBACK, SafeContentType::for('application/x-shellscript'));

        // ...and ordinary types are left alone.
        $this->assertSame('image/png', SafeContentType::for('image/png'));
        $this->assertSame('image/jpeg', SafeContentType::for('IMAGE/JPEG'));
    }

    public function test_a_unicode_filename_survives_the_download_header(): void
    {
        $disposition = StorageSignedUrlIssuer::contentDisposition('فاتورة-٢٠٢٦.pdf');

        // Both forms: a client understanding neither would otherwise save the
        // file under a row of question marks.
        $this->assertStringContainsString("filename*=UTF-8''", $disposition);
        $this->assertMatchesRegularExpression('/filename="[^"]*"/', $disposition);
    }

    public function test_a_filename_cannot_break_out_of_the_header(): void
    {
        $disposition = StorageSignedUrlIssuer::contentDisposition('evil".pdf');

        // A quote in the ASCII fallback would end the parameter early and let
        // the rest of the name become header syntax.
        $this->assertStringNotContainsString('evil".pdf', $disposition);
    }

    public function test_an_expired_link_is_refused(): void
    {
        $attachment = $this->attachment(ScanStatus::Clean);
        $location = (string) $this->get("/api/v1/attachments/{$attachment->getKey()}/download")
            ->headers->get('Location');

        $this->travel(10)->minutes();

        $this->get($location)->assertStatus(403);
    }

    public function test_a_link_stops_working_once_the_file_is_quarantined_again(): void
    {
        $attachment = $this->attachment(ScanStatus::Clean);
        $location = (string) $this->get("/api/v1/attachments/{$attachment->getKey()}/download")
            ->headers->get('Location');

        $attachment->forceFill(['scan_status' => ScanStatus::Failed->value])->save();

        // A valid signature proves the link was issued, not that the file is
        // still safe to hand over.
        $this->get($location)->assertStatus(403);
    }

    public function test_a_missing_attachment_is_a_problem_document(): void
    {
        $this->getJson('/api/v1/attachments/01JZZZZZZZZZZZZZZZZZZZZZZZ/download')
            ->assertStatus(404)
            ->assertJsonPath('code', 'platform.attachment_not_found');
    }

    public function test_there_is_no_inline_preview_route(): void
    {
        $attachment = $this->attachment(ScanStatus::Clean);

        // Serving uploaded content inline from a trusted origin is a stored XSS
        // that no virus scanner would flag. The route does not exist.
        $this->getJson("/api/v1/attachments/{$attachment->getKey()}/preview")->assertStatus(404);
        $this->getJson("/api/v1/attachments/{$attachment->getKey()}/inline")->assertStatus(404);
    }
}
