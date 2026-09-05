<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Attachments\Application\AttachmentUploader;
use App\Modules\Platform\Attachments\Application\SafeContentType;
use App\Modules\Platform\Attachments\Application\SignedUrlIssuer;
use App\Modules\Platform\Attachments\Domain\Attachment;
use App\Modules\Platform\Attachments\Domain\AttachmentOwnerType;
use App\Modules\Platform\Attachments\Http\AttachmentPresenter;
use App\Modules\Platform\Attachments\Http\Requests\StoreAttachmentRequest;
use App\Modules\Platform\Exceptions\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AttachmentsController extends Controller
{
    public function __construct(
        private readonly AttachmentUploader $uploader,
        private readonly SignedUrlIssuer $urls,
    ) {}

    /**
     * Everything attached to one record.
     *
     * @response array{data: array<int, array<string, mixed>>}
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'owner_type' => ['required', 'string'],
            'owner_id' => ['required', 'string', 'size:26'],
        ]);

        $type = AttachmentOwnerType::tryFrom((string) $validated['owner_type']);

        if ($type === null) {
            throw ProblemException::make(
                'platform.attachment_owner_unknown',
                'Unknown attachment owner',
                422,
                'Attachments belong to a customer, a ticket or a message.',
            );
        }

        $attachments = Attachment::query()
            ->for($type, (string) $validated['owner_id'])
            ->orderByDesc('uploaded_at')
            ->get();

        return new JsonResponse([
            'data' => $attachments->map(static fn (Attachment $a): array => AttachmentPresenter::full($a))->all(),
        ]);
    }

    /**
     * @response array<string, mixed>
     */
    public function store(StoreAttachmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $attachment = $this->uploader->upload(
            $request->file('file'),
            AttachmentOwnerType::from((string) $validated['owner_type']),
            (string) $validated['owner_id'],
            $request->user()?->getAuthIdentifier() !== null
                ? (string) $request->user()->getAuthIdentifier()
                : null,
        );

        // 201 even though nothing is downloadable yet. The upload really did
        // happen; whether the file is safe is a separate question with its own
        // state, and blocking the response on a scan would tie the request to
        // a daemon's availability.
        return new JsonResponse(AttachmentPresenter::full($attachment), 201);
    }

    /**
     * @response array<string, mixed>
     */
    public function show(string $id): JsonResponse
    {
        return new JsonResponse(AttachmentPresenter::full($this->find($id)));
    }

    /**
     * A short-lived link, or a refusal.
     *
     * Never streams the bytes through this application in production: the
     * redirect points at storage, which validates the signature itself. That
     * keeps large files off the web process entirely.
     */
    public function download(string $id): RedirectResponse
    {
        $attachment = $this->find($id);

        if (! $attachment->isDownloadable()) {
            throw ProblemException::make(
                'platform.attachment_not_downloadable',
                'This file is not available',
                403,
                $attachment->status()->value === 'failed'
                    ? 'This file did not pass its security scan, so it cannot be downloaded.'
                    : 'This file is still being scanned. It will be available once the scan finishes.',
                ['scan_status' => $attachment->status()->value],
            );
        }

        $url = $this->urls->issue(
            $attachment->stored_path,
            $attachment->filename,
            // Coerced away from anything a browser would execute — see
            // SafeContentType. A clean file can still be a stored XSS.
            SafeContentType::for($attachment->mime_type),
            now()->addMinutes((int) config('attachments.signed_url_minutes', 5)),
        );

        // no-store: the redirect carries a credential in its query string, and
        // a cached 302 would hand it to the next person on a shared machine.
        return redirect()->away($url)->header('Cache-Control', 'no-store, private');
    }

    private function find(string $id): Attachment
    {
        $attachment = Attachment::query()->find($id);

        if ($attachment === null) {
            throw ProblemException::make(
                'platform.attachment_not_found',
                'Attachment not found',
                404,
                "No attachment with id [{$id}].",
            );
        }

        return $attachment;
    }

    /**
     * @return array<string, mixed>
     */
}
