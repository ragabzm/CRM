<?php

declare(strict_types=1);

namespace Tests\Feature\Platform\Attachments;

use App\Modules\Platform\Attachments\Domain\Attachment;
use App\Modules\Platform\Attachments\Domain\Scanning\FileScanner;
use App\Modules\Platform\Attachments\Domain\Scanning\ScannerUnreachable;
use App\Modules\Platform\Attachments\Domain\Scanning\ScanResult;
use App\Modules\Platform\Attachments\Domain\ScanStatus;
use App\Modules\Platform\Attachments\Jobs\ScanAttachmentJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Security\InteractsWithSpaSession;
use Tests\TestCase;

/**
 * The three outcomes, and what each leaves behind.
 */
final class ScanAttachmentJobTest extends TestCase
{
    use InteractsWithAttachments;
    use InteractsWithSpaSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAttachments();
    }

    /**
     * Uploads without letting the job run.
     *
     * The test queue is synchronous, so a real dispatch would scan during the
     * upload request — and these tests are about running the job deliberately,
     * one state at a time.
     */
    private function uploaded(): Attachment
    {
        Queue::fake();

        $this->withIdempotencyKey()->post('/api/v1/attachments', [
            'owner_type' => 'customer',
            'owner_id' => $this->ownerId(),
            'file' => $this->pngFile(),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        return Attachment::query()->firstOrFail();
    }

    private function scannerReturning(ScanResult $result): void
    {
        $this->swap(FileScanner::class, new class($result) implements FileScanner
        {
            public function __construct(private readonly ScanResult $result) {}

            public function scan(string $absolutePath): ScanResult
            {
                return $this->result;
            }
        });
    }

    private function scannerThatIsDown(): void
    {
        $this->swap(FileScanner::class, new class implements FileScanner
        {
            public function scan(string $absolutePath): ScanResult
            {
                throw new ScannerUnreachable('connection refused');
            }
        });
    }

    private function runScan(Attachment $attachment): void
    {
        $this->app->call([new ScanAttachmentJob($attachment->getKey()), 'handle']);
    }

    public function test_a_clean_file_moves_out_of_quarantine_and_becomes_downloadable(): void
    {
        $this->scannerReturning(ScanResult::clean());
        $attachment = $this->uploaded();

        $this->runScan($attachment);

        $attachment->refresh();
        $this->assertSame(ScanStatus::Clean, $attachment->status());
        $this->assertTrue($attachment->isDownloadable());
        $this->assertNotNull($attachment->scanned_at);

        $id = $attachment->getKey();
        Storage::disk('attachments')->assertExists("clean/{$id}");
        Storage::disk('attachments')->assertMissing("quarantine/{$id}");
        $this->assertSame("clean/{$id}", $attachment->stored_path);
    }

    public function test_a_failed_file_stays_in_quarantine_and_keeps_its_reason(): void
    {
        $this->scannerReturning(ScanResult::failed('Eicar-Test-Signature'));
        $attachment = $this->uploaded();

        $this->runScan($attachment);

        $attachment->refresh();
        $this->assertSame(ScanStatus::Failed, $attachment->status());
        $this->assertFalse($attachment->isDownloadable());
        $this->assertSame('Eicar-Test-Signature', $attachment->scan_result['reason']);

        // NOT deleted: an incident review needs the evidence, and deleting the
        // file would also delete the record of what was uploaded.
        $id = $attachment->getKey();
        Storage::disk('attachments')->assertExists("quarantine/{$id}");
        Storage::disk('attachments')->assertMissing("clean/{$id}");
    }

    public function test_an_unreachable_scanner_leaves_the_file_exactly_as_it_was(): void
    {
        $this->scannerThatIsDown();
        $attachment = $this->uploaded();

        // Does not throw: an outage is an expected condition, and the row is
        // already in the right state for it.
        $this->runScan($attachment);

        $attachment->refresh();

        /*
         * Pending, not failed and certainly not clean. Treating an outage as
         * clean would turn a scanner going down into a delivery mechanism;
         * treating it as failed would tell a customer their invoice contains
         * a virus.
         */
        $this->assertSame(ScanStatus::Pending, $attachment->status());
        $this->assertFalse($attachment->isDownloadable());
        $this->assertNull($attachment->scanned_at);
        Storage::disk('attachments')->assertExists('quarantine/'.$attachment->getKey());
    }

    public function test_an_upload_still_succeeds_while_the_scanner_is_down(): void
    {
        $this->scannerThatIsDown();

        /*
         * Deliberately NOT faking the queue. The test connection is
         * synchronous, so the job really runs inside this request — which is
         * the worst case, and exactly the one the guarantee is about: a
         * scanner outage must not become "uploads are broken".
         */
        $response = $this->withIdempotencyKey()->post('/api/v1/attachments', [
            'owner_type' => 'customer',
            'owner_id' => $this->ownerId(),
            'file' => $this->pngFile(),
        ], ['Accept' => 'application/json'])->assertStatus(201);

        $response->assertJsonPath('scan_status', 'pending')
            ->assertJsonPath('downloadable', false);

        Storage::disk('attachments')->assertExists('quarantine/'.$response->json('id'));
    }

    public function test_the_job_retries_enough_to_ride_out_a_restart(): void
    {
        $job = new ScanAttachmentJob('01AAAAAAAAAAAAAAAAAAAAAAAA');

        // Five attempts at thirty seconds: long enough for a scanner restart,
        // short enough that a dead one does not fill the queue.
        $this->assertSame(5, $job->tries);
        $this->assertSame(30, $job->backoff);
    }

    public function test_a_second_run_after_a_verdict_changes_nothing(): void
    {
        $this->scannerReturning(ScanResult::failed('Eicar-Test-Signature'));
        $attachment = $this->uploaded();

        $this->runScan($attachment);

        // A duplicate dispatch must not re-open a decided file.
        $this->scannerReturning(ScanResult::clean());
        $this->runScan($attachment);

        $this->assertSame(ScanStatus::Failed, $attachment->refresh()->status());
        Storage::disk('attachments')->assertMissing('clean/'.$attachment->getKey());
    }

    public function test_a_retry_after_the_move_succeeded_finishes_the_job(): void
    {
        $this->scannerReturning(ScanResult::clean());
        $attachment = $this->uploaded();
        $id = $attachment->getKey();

        // Simulates a worker crash between the move and the update: the file is
        // already at its destination and the row still says pending.
        Storage::disk('attachments')->move("quarantine/{$id}", "clean/{$id}");
        $this->assertSame(ScanStatus::Pending, $attachment->refresh()->status());

        $this->runScan($attachment);

        // Finishes rather than failing forever on "source missing".
        $this->assertSame(ScanStatus::Clean, $attachment->refresh()->status());
        Storage::disk('attachments')->assertExists("clean/{$id}");
    }

    public function test_a_deleted_attachment_is_not_an_error(): void
    {
        $this->scannerReturning(ScanResult::clean());
        $attachment = $this->uploaded();
        $id = $attachment->getKey();
        $attachment->delete();

        // Queued work outliving its subject is ordinary, not exceptional.
        $this->app->call([new ScanAttachmentJob($id), 'handle']);

        $this->assertDatabaseCount('attachments', 0);
    }

    public function test_the_default_scanner_accepts_everything_and_says_so(): void
    {
        // Documents the CI posture explicitly: nothing is really being scanned
        // in the test suite, and that is a named choice.
        $this->assertInstanceOf(
            \App\Modules\Platform\Attachments\Infrastructure\NullFileScanner::class,
            $this->app->make(FileScanner::class),
        );
    }
}
