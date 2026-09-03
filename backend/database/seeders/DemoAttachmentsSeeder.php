<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Platform\Attachments\Application\AttachmentUploader;
use App\Modules\Platform\Attachments\Domain\Attachment;
use App\Modules\Platform\Attachments\Domain\AttachmentOwnerType;
use App\Modules\Platform\Attachments\Domain\Scanning\FileScanner;
use App\Modules\Platform\Attachments\Domain\ScanStatus;
use App\Modules\Platform\Attachments\Jobs\ScanAttachmentJob;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Two real files, on disk, downloadable.
 *
 * Real is the requirement. A row in `attachments` pointing at bytes that are
 * not there looks identical to a working attachment in every list and every
 * count — right up to the moment somebody clicks it, which is the one moment
 * the demo data existed for.
 *
 * So both go through `AttachmentUploader`, the same path an upload takes:
 * sniffed type, size cap, written to quarantine, then scanned. In local and
 * testing the scanner is `NullFileScanner`, so the verdict is clean and the
 * file moves to the clean prefix where the download path can find it.
 *
 * The scan is run inline rather than left to the queue. `ScanAttachmentJob` is
 * dispatched by the uploader, but whether it RUNS depends on the queue driver
 * — on `database` it sits there until a worker picks it up, and a seeded
 * dataset would be handed to a developer with two files stuck in quarantine
 * and no obvious reason why.
 */
final class DemoAttachmentsSeeder extends Seeder
{
    private const TICKET_SUBJECT = 'الفاتورة فيها رسم مكرر';

    private const TICKET_CUSTOMER = 'layla.haddad@example.test';

    private const CUSTOMER_EMAIL = 'sarah.nasser@example.test';

    public function run(): void
    {
        if (! DemoEnvironment::allows($this->command, self::class)) {
            return;
        }

        DemoEnvironment::needs($this, DemoCustomersSeeder::class, static fn (): bool => \App\Modules\Customers\Domain\Customer::query()->exists());
        DemoEnvironment::needs($this, DemoTicketsSeeder::class, static fn (): bool => \App\Modules\Tickets\Domain\Ticket::query()->exists());

        $seeded = 0;

        $customer = DemoCustomersSeeder::findByEmail(self::TICKET_CUSTOMER);
        $ticket = $customer === null
            ? null
            : DemoTicketsSeeder::existing(self::TICKET_SUBJECT, (string) $customer->getKey());

        if ($ticket !== null) {
            $seeded += (int) $this->attach(
                AttachmentOwnerType::Ticket,
                (string) $ticket->getKey(),
                'demo-ticket-attachment.pdf',
                'application/pdf',
            );
        }

        $owner = DemoCustomersSeeder::findByEmail(self::CUSTOMER_EMAIL);

        if ($owner !== null) {
            $seeded += (int) $this->attach(
                AttachmentOwnerType::Customer,
                (string) $owner->getKey(),
                'demo-customer-avatar.png',
                'image/png',
            );
        }

        $this->command?->info("Seeded {$seeded} demo attachments (scanned clean, files on disk).");
    }

    private function attach(
        AttachmentOwnerType $ownerType,
        string $ownerId,
        string $filename,
        string $mime,
    ): bool {
        $source = __DIR__.'/fixtures/'.$filename;

        if (! is_file($source)) {
            $this->command?->warn("Fixture missing: {$filename}");

            return false;
        }

        $existing = Attachment::query()
            ->where('owner_type', $ownerType->value)
            ->where('owner_id', $ownerId)
            ->where('filename', $filename)
            ->first();

        if ($existing !== null) {
            // The row is here. Whether the FILE is here is a separate
            // question: wiping `storage/app/attachments` is a normal thing to
            // do and leaves the row behind. Put the bytes back, keep the row,
            // and do not mint a second attachment.
            $this->restoreIfMissing($existing, $source);

            return false;
        }

        /*
         * `$test: true` is what lets an UploadedFile be built from a path that
         * did not arrive over HTTP. Without it, PHP refuses to treat the file
         * as uploaded and `getSize()`/`getMimeType()` fail.
         */
        $attachment = app(AttachmentUploader::class)->upload(
            new UploadedFile($source, $filename, $mime, null, true),
            $ownerType,
            $ownerId,
            uploaderId: null,
        );

        if ($attachment->status() === ScanStatus::Pending) {
            (new ScanAttachmentJob((string) $attachment->getKey()))->handle(app(FileScanner::class));
        }

        return true;
    }

    private function restoreIfMissing(Attachment $attachment, string $source): void
    {
        $disk = Storage::disk((string) config('attachments.disk'));

        if ($disk->exists($attachment->stored_path)) {
            return;
        }

        $disk->put($attachment->stored_path, (string) file_get_contents($source));

        $this->command?->warn("Restored missing file for attachment {$attachment->getKey()}.");
    }
}
