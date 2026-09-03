<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Requests;

use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A partial change. Every attribute optional, the version required.
 *
 * The version is required even though the guard would tolerate null, because a
 * caller who omitted it would be silently opting out of the protection this
 * whole mechanism exists for.
 *
 * It may arrive in the body as `version` or in an `If-Match` header — the same
 * number, spelled the way the caller finds natural. There is still ONE guard
 * reading ONE value; a second mechanism would be a second thing to get wrong,
 * and the one that is wrong is the one nobody tested.
 */
final class UpdateTicketAttributesRequest extends FormRequest
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
            // Required only when the header did not carry it.
            'version' => [Rule::requiredIf($this->versionFromHeader() === null), 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(TicketStatus::values())],
            'priority' => ['sometimes', Rule::in(Priority::values())],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('ticket_categories', 'id')],
            'assignee_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'department_id' => ['sometimes', 'nullable', 'integer', Rule::exists('departments', 'id')],
        ];
    }

    /**
     * The version the caller believes the ticket is at.
     *
     * Body first: an explicit field beats a header when both are present,
     * because the field is what the form actually submitted.
     */
    public function submittedVersion(): ?int
    {
        $body = $this->validated('version');

        if ($body !== null) {
            return (int) $body;
        }

        return $this->versionFromHeader();
    }

    /**
     * Reads `If-Match`, in either spelling.
     *
     * `W/"3"` is what a client echoes back from a weak ETag; `3` is what one
     * sends when it built the header by hand. Refusing the second would be
     * pedantry that costs a working request.
     */
    private function versionFromHeader(): ?int
    {
        $header = trim((string) $this->header('If-Match', ''));

        if ($header === '' || $header === '*') {
            return null;
        }

        if (preg_match('/(\d+)/', $header, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
