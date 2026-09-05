<?php

declare(strict_types=1);

namespace App\Modules\Channels\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Attachments\Application\AttachmentUploader;
use App\Modules\Platform\Attachments\Domain\AttachmentOwnerType;
use App\Modules\Platform\Attachments\Http\AttachmentPresenter;
use App\Modules\Platform\Exceptions\ProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The public form's upload door.
 *
 * A separate route rather than an exemption on `POST /api/attachments`, for two
 * reasons that both matter.
 *
 * The existing endpoint sits behind `auth:web`, and the person filling in the
 * public form has no session. Punching a hole in that group would make the
 * authenticated uploader conditionally public, which is the kind of change that
 * is correct on the day it is made and forgotten by the next person to read the
 * group.
 *
 * And the check that makes an anonymous upload safe — "this draft token exists
 * and has not expired" — cannot live in Platform. Platform is T0; the token is
 * a Channels concept. A query from there into this module would invert the
 * dependency graph.
 *
 * What it does NOT do is upload differently: the same `AttachmentUploader`, the
 * same size and type settings, the same quarantine and the same scan job. There
 * is one uploader in this system and this is not a second one.
 */
final class WebFormAttachmentController extends Controller
{
    public function __construct(private readonly AttachmentUploader $uploader) {}

    /**
     * @response array<string, mixed>
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_token' => ['required', 'string', 'size:26'],
            'file' => ['required', 'file'],
        ]);

        $token = (string) $validated['session_token'];

        $live = DB::table('web_form_sessions')
            ->where('id', $token)
            ->where('expires_at', '>', now())
            ->exists();

        if (! $live) {
            throw ProblemException::make(
                'channels.web_form_session_expired',
                'This form has been open too long',
                422,
                'Please reload the page and attach your file again.',
            );
        }

        $attachment = $this->uploader->upload(
            $request->file('file'),
            AttachmentOwnerType::WebFormDraft,
            $token,
            // Nobody signed in. The uploader is whoever filled in the form,
            // and they have no identity in this system until the ticket exists.
            uploaderId: null,
        );

        return new JsonResponse(AttachmentPresenter::summary($attachment), 201);
    }

}
