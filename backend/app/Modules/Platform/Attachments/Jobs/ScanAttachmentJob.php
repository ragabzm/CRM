<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Jobs;

use App\Modules\Platform\Attachments\Domain\Attachment;
use App\Modules\Platform\Attachments\Domain\Scanning\FileScanner;
use App\Modules\Platform\Attachments\Domain\Scanning\ScannerUnreachable;
use App\Modules\Platform\Attachments\Domain\ScanStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Moves an attachment out of quarantine, or leaves it there.
 *
 * Three outcomes, and the difference between them is the point:
 *
 *   clean    — the file moves to clean/ and becomes downloadable.
 *   failed   — the file STAYS in quarantine/ and never becomes downloadable.
 *              It is not deleted: an incident review needs the evidence, and
 *              deleting it would also delete the record of what was uploaded.
 *   unreachable — nothing changes. The file stays pending and undownloadable,
 *              which is the same outward behaviour as "not scanned yet",
 *              because that is exactly what it is.
 *
 * The last one is the safety property. Treating an outage as clean turns a
 * scanner going down into a delivery mechanism.
 */
final class ScanAttachmentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Five attempts over roughly two and a half minutes.
     *
     * Enough to ride out a restart, few enough that a genuinely dead scanner
     * does not fill the queue. Exhausting them leaves the file quarantined,
     * which is the correct resting state for something nobody could check.
     */
    public int $tries = 5;

    public int $backoff = 30;

    public function __construct(public readonly string $attachmentId) {}

    public function handle(FileScanner $scanner): void
    {
        $attachment = Attachment::query()->find($this->attachmentId);

        if ($attachment === null) {
            // Deleted while queued. Nothing to do, and not an error.
            return;
        }

        if ($attachment->status() !== ScanStatus::Pending) {
            // Already judged — a duplicate dispatch, or a retry after the
            // update committed. Re-scanning would be harmless but pointless.
            return;
        }

        $disk = Storage::disk((string) config('attachments.disk'));

        try {
            $result = $scanner->scan($disk->path($attachment->stored_path));
        } catch (ScannerUnreachable $e) {
            Log::warning('attachment.scan.unreachable', [
                'attachment_id' => $attachment->getKey(),
                'exception' => $e->getMessage(),
            ]);

            /*
             * Swallowed, never re-thrown.
             *
             * An unreachable scanner is an expected condition, not a bug, and
             * the row is already in the right state for it: pending, in
             * quarantine, undownloadable. Throwing would mark the job failed —
             * and on a synchronous queue (a small deployment, or a
             * misconfiguration) the exception would surface inside the upload
             * request and turn a scanner outage into "uploads are broken",
             * which is precisely what this design exists to prevent.
             *
             * Retried by hand rather than by throwing: release() puts it back
             * with a delay when there is a queue to put it back on, and does
             * nothing when the job is running inline.
             */
            if ($this->job !== null) {
                $this->release($this->backoff);
            }

            return;
        }

        if (! $result->clean) {
            $attachment->forceFill([
                'scan_status' => ScanStatus::Failed->value,
                'scan_result' => ['reason' => $result->reason, 'raw' => $result->raw],
                'scanned_at' => now(),
            ])->save();

            Log::warning('attachment.scan.failed', [
                'attachment_id' => $attachment->getKey(),
                'reason' => $result->reason,
            ]);

            return;
        }

        $this->promote($attachment, $disk);
    }

    /**
     * Move to clean/, then record it.
     *
     * That order matters. If the move succeeds and the update does not, the
     * file is readable but the row still says pending — and pending is not
     * downloadable, so the failure is safe and the retry fixes it. The reverse
     * order would mark a file downloadable while it was still in quarantine.
     */
    private function promote(Attachment $attachment, \Illuminate\Contracts\Filesystem\Filesystem $disk): void
    {
        $target = config('attachments.prefixes.clean').'/'.$attachment->getKey();

        if (! $disk->exists($target)) {
            try {
                $disk->move($attachment->stored_path, $target);
            } catch (Throwable $e) {
                // A crash between the move and the update leaves the file at
                // the destination and the row pending; the retry finds it
                // already there and carries on rather than failing forever.
                if (! $disk->exists($target)) {
                    throw $e;
                }
            }
        }

        $attachment->forceFill([
            'stored_path' => $target,
            'scan_status' => ScanStatus::Clean->value,
            'scan_result' => null,
            'scanned_at' => now(),
        ])->save();
    }
}
