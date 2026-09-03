<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Contracts;

/**
 * Everything the portal is allowed to do with a customer's requests.
 *
 * Narrow on purpose, and narrow in two directions at once.
 *
 * WHAT IT CAN DO: five operations, all scoped to one customer id that the
 * CALLER never chooses — the portal passes the id off the signed-in account,
 * and every method filters by it. There is no "list all", no "find by id"
 * without a customer, and no way to ask about somebody else.
 *
 * WHAT IT RETURNS: primitives, already stripped. The portal never receives an
 * internal note, an SLA reading, or an agent's own activity — not because the
 * portal chooses not to render them, but because they never cross this
 * boundary. A UI that filters is a UI one bug away from not filtering.
 */
interface CustomerRequestGateway
{
    /**
     * The customer's own requests, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function list(string $customerId): array;

    /**
     * One request with its customer-visible thread, or null.
     *
     * Null — not a refusal — when the request belongs to somebody else. The
     * caller turns that into a 404, because a 403 would confirm the id exists
     * and let somebody map the business by guessing.
     *
     * @return array<string, mixed>|null
     */
    public function show(string $customerId, string $ticketId): ?array;

    /**
     * Opens a request on the customer's behalf.
     *
     * @param  list<string>  $attachmentIds  Already uploaded against the customer.
     * @return array<string, mixed>
     */
    public function submit(
        string $customerId,
        string $accountId,
        string $accountName,
        string $subject,
        string $description,
        ?int $categoryId,
        array $attachmentIds,
    ): array;

    /**
     * Appends the customer's reply.
     *
     * @param  list<string>  $attachmentIds
     * @return array<string, mixed>|null  Null when the request is not theirs.
     */
    public function reply(
        string $customerId,
        string $accountId,
        string $accountName,
        string $ticketId,
        string $body,
        array $attachmentIds,
    ): ?array;

    /**
     * Reopens a closed request, within whatever window is configured.
     *
     * Throws the module's own refusal past the window rather than returning a
     * flag: the reason and the remaining time live in Tickets, and a boolean
     * would make the portal invent an explanation.
     *
     * @return array<string, mixed>|null  Null when the request is not theirs.
     */
    public function reopen(string $customerId, string $accountId, string $accountName, string $ticketId): ?array;
}
