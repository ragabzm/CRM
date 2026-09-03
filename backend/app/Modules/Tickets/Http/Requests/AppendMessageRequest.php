<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Requests;

use App\Modules\Tickets\Domain\Enum\MessageDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * No `version` rule, deliberately — see AppendMessage.
 */
final class AppendMessageRequest extends FormRequest
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
            'body' => ['required', 'string', 'min:1', 'max:20000'],
            'direction' => ['sometimes', Rule::in(MessageDirection::values())],

            /*
             * Ids of files already uploaded against this ticket. Sent as a list
             * rather than as the files themselves so a slow or refused upload
             * never costs the agent the reply they typed.
             */
            'attachment_ids' => ['sometimes', 'array', 'max:10'],
            'attachment_ids.*' => ['string', 'size:26'],
        ];
    }

    /** @return list<string> */
    public function attachmentIds(): array
    {
        /** @var list<string> $ids */
        $ids = $this->validated('attachment_ids', []);

        // Duplicates would try to claim the same file twice and fail the
        // all-or-nothing count, refusing a reply that was actually fine.
        return array_values(array_unique($ids));
    }
}
