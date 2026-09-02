<?php

declare(strict_types=1);

namespace App\Modules\Platform\Attachments\Http\Requests;

use App\Modules\Platform\Attachments\Domain\AttachmentOwnerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'owner_type' => ['required', Rule::in(AttachmentOwnerType::values())],
            /*
             * ULID-shaped, and that is as far as the check goes.
             *
             * Platform is T0 and cannot look inside Customers or Tickets to
             * confirm the owner exists — a query from here would invert the
             * dependency graph. The owning module checks existence when it
             * builds the upload, and an attachment pointing at nothing is
             * invisible rather than dangerous: it is only ever read through
             * "everything attached to THIS record".
             */
            'owner_id' => ['required', 'string', 'size:26'],

            /*
             * No `max` and no `mimes` here on purpose. The size cap and the
             * allow-list are runtime settings an administrator controls, and
             * baking them into a validation rule would freeze them at deploy
             * time. AttachmentUploader applies both, against the current values.
             */
            'file' => ['required', 'file'],
        ];
    }
}
