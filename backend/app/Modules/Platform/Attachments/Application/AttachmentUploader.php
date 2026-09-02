<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Application;

use App\Modules\Platform\Attachments\Domain\Attachment;
use App\Modules\Platform\Attachments\Domain\AttachmentOwnerType;
use App\Modules\Platform\Attachments\Domain\ScanStatus;
use App\Modules\Platform\Attachments\Jobs\ScanAttachmentJob;
use App\Modules\Platform\Exceptions\ProblemException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Takes a file, checks it, and puts it somewhere nobody can read yet.
 *
 * The order matters: every check runs BEFORE anything is written. A rejected
 * upload must leave no bytes on disk, or the rejection is only a UI opinion.
 */
final class AttachmentUploader
{
    public function __construct(private readonly AttachmentSettings $settings) {}

    public function upload(
        UploadedFile $file,
        AttachmentOwnerType $ownerType,
        string $ownerId,
        ?string $uploaderId,
    ): Attachment {
        $this->assertWithinSizeCap($file);
        $mime = $this->sniff($file);
        $this->assertAllowed($mime, $file);

        $disk = config('attachments.disk');
        $id = (string) Str::ulid();
        $path = config('attachments.prefixes.quarantine').'/'.$id;

        /*
         * Written under quarantine/ and nowhere else. The prefix IS the state:
         * a file that has not been scanned cannot be in the directory the
         * download path reads from, so a bug in the state machine cannot make
         * an unscanned file reachable.
         */
        Storage::disk($disk)->putFileAs(
            config('attachments.prefixes.quarantine'),
            $file,
            $id,
        );

        /*
         * The key is set explicitly rather than passed to create(): `id` is not
         * fillable, so mass assignment silently drops it and HasUlids mints a
         * different one — leaving the row pointing at a path that does not
         * exist.
         */
        $attachment = new Attachment([
            'owner_type' => $ownerType->value,
            'owner_id' => $ownerId,
            // The name as given, including non-Latin scripts. Escaped where
            // used, never mangled here.
            'filename' => $file->getClientOriginalName(),
            'stored_path' => $path,
            'byte_size' => $file->getSize(),
            // The SNIFFED type. The client's claim is not stored anywhere.
            'mime_type' => $mime,
            'uploader_id' => $uploaderId,
            'uploaded_at' => now(),
            'scan_status' => ScanStatus::Pending->value,
        ]);

        $attachment->setAttribute($attachment->getKeyName(), $id);
        $attachment->save();

        ScanAttachmentJob::dispatch($attachment->getKey());

        return $attachment;
    }

    private function assertWithinSizeCap(UploadedFile $file): void
    {
        $max = $this->settings->maxBytes();
        $size = (int) $file->getSize();

        if ($size > $max) {
            throw ProblemException::make(
                'platform.attachment_too_large',
                'File is too large',
                422,
                sprintf('That file is %s. The limit is %s.', self::human($size), self::human($max)),
                ['max_bytes' => $max, 'byte_size' => $size],
            );
        }
    }

    /**
     * What the file actually is, according to its own contents.
     *
     * `finfo` on the temporary path, never `getClientMimeType()`. The client's
     * value is a claim typed by whoever is uploading — trusting it is the same
     * as having no allow-list, because a shell script announced as image/png
     * would sail through one.
     */
    private function sniff(UploadedFile $file): string
    {
        $detected = $file->getMimeType();

        if (! is_string($detected) || $detected === '') {
            throw ProblemException::make(
                'platform.attachment_type_unknown',
                'File type could not be determined',
                422,
                'We could not tell what kind of file that is, so it was not accepted.',
            );
        }

        return strtolower(explode(';', $detected)[0]);
    }

    private function assertAllowed(string $sniffed, UploadedFile $file): void
    {
        $allowed = $this->settings->allowedMimeTypes();

        if (! in_array($sniffed, $allowed, true)) {
            throw ProblemException::make(
                'platform.attachment_type_not_allowed',
                'File type is not accepted',
                422,
                sprintf('Files of type %s are not accepted here.', $sniffed),
                ['detected_mime_type' => $sniffed, 'allowed_mime_types' => array_values($allowed)],
            );
        }

        $claimed = strtolower(explode(';', (string) $file->getClientMimeType())[0]);

        /*
         * The claim disagreeing with the contents is refused even when the
         * contents are allowed. It is not dangerous by itself — but it is
         * either a broken client or a deliberate attempt, and both are worth
         * refusing loudly rather than quietly accepting.
         */
        if ($claimed !== '' && $claimed !== $sniffed) {
            throw ProblemException::make(
                'platform.attachment_mime_mismatch',
                'File contents do not match its type',
                422,
                sprintf('That file says it is %s but its contents are %s.', $claimed, $sniffed),
                ['claimed_mime_type' => $claimed, 'detected_mime_type' => $sniffed],
            );
        }
    }

    private static function human(int $bytes): string
    {
        $units = ['bytes', 'KB', 'MB', 'GB'];
        $index = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return ($index === 0 ? (string) (int) $value : number_format($value, 1)).' '.$units[$index];
    }
}
