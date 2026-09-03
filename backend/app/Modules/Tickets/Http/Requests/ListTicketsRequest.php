<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Requests;

use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Priority;
use App\Modules\Tickets\Domain\Query\TicketListFilters;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * What the list will accept from a query string.
 *
 * Everything optional, everything whitelisted. A sort column is an ORDER BY
 * clause the caller chose, and an unbounded page size is a way to ask the
 * server for the whole table — neither is something to pass through.
 */
final class ListTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Comma-separated is what a URL naturally carries.
     *
     * `?status=open,pending` is what a link in the counts strip looks like and
     * what an agent can read and edit in the address bar. Split before
     * validation so the rules below see arrays either way.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'status' => $this->split('status'),
            'priority' => $this->split('priority'),
            'category_id' => $this->split('category_id'),
            'assignee_id' => $this->split('assignee_id'),
            'department_id' => $this->split('department_id'),
        ], static fn (?array $value): bool => $value !== null));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'array'],
            'status.*' => [Rule::in(TicketStatus::values())],

            'priority' => ['sometimes', 'array'],
            'priority.*' => [Rule::in(Priority::values())],

            'category_id' => ['sometimes', 'array'],
            'category_id.*' => ['integer'],

            'assignee_id' => ['sometimes', 'array'],
            // Either a user id or the "nobody has picked this up" sentinel.
            'assignee_id.*' => ['string'],

            'department_id' => ['sometimes', 'array'],
            'department_id.*' => ['integer'],

            'created_from' => ['sometimes', 'date'],
            'created_to' => ['sometimes', 'date'],

            'q' => ['sometimes', 'nullable', 'string', 'max:200'],

            'sort' => ['sometimes', Rule::in(TicketListFilters::SORTABLE)],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],

            // Capped rather than unbounded: a caller asking for ten thousand
            // rows gets a page, not an outage.
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.TicketListFilters::MAX_PER_PAGE],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->input('created_from');
            $to = $this->input('created_to');

            if ($from === null || $to === null) {
                return;
            }

            if (strtotime((string) $from) > strtotime((string) $to)) {
                /*
                 * Refused rather than quietly swapped. A reversed range is
                 * almost always a typo, and silently correcting it would show
                 * results for a period nobody asked about.
                 */
                $validator->errors()->add('created_to', 'The end of the range must not be before its start.');
            }
        });
    }

    public function toFilters(): TicketListFilters
    {
        /** @var array<string, mixed> $data */
        $data = $this->validated();

        $term = trim((string) ($data['q'] ?? ''));

        return new TicketListFilters(
            status: array_values(array_map('strval', $data['status'] ?? [])),
            priority: array_values(array_map('strval', $data['priority'] ?? [])),
            categoryIds: array_values(array_map('intval', $data['category_id'] ?? [])),
            assigneeIds: $this->assignees($data['assignee_id'] ?? []),
            departmentIds: array_values(array_map('intval', $data['department_id'] ?? [])),
            createdFrom: isset($data['created_from']) ? (string) $data['created_from'] : null,
            // The whole of the closing day, not up to its first second.
            createdTo: isset($data['created_to'])
                ? date('Y-m-d 23:59:59', (int) strtotime((string) $data['created_to']))
                : null,
            // Whitespace is not a search; treated as absent rather than as a
            // term that matches everything.
            term: $term === '' ? null : $term,
            sort: (string) ($data['sort'] ?? 'updated_at'),
            direction: (string) ($data['direction'] ?? 'desc'),
            perPage: (int) ($data['per_page'] ?? TicketListFilters::DEFAULT_PER_PAGE),
        );
    }

    /**
     * @param  array<int, mixed>  $raw
     * @return list<int|string>
     */
    private function assignees(array $raw): array
    {
        return array_values(array_map(
            static fn (mixed $id): int|string => (string) $id === TicketListFilters::UNASSIGNED
                ? TicketListFilters::UNASSIGNED
                : (int) $id,
            $raw,
        ));
    }

    /** @return list<string>|null */
    private function split(string $key): ?array
    {
        $raw = $this->query($key);

        if ($raw === null) {
            return null;
        }

        if (is_array($raw)) {
            return array_values(array_map('strval', $raw));
        }

        return array_values(array_filter(
            array_map('trim', explode(',', (string) $raw)),
            static fn (string $part): bool => $part !== '',
        ));
    }
}
