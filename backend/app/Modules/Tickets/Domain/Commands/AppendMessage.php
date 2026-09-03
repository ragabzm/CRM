<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Commands;

use App\Modules\Platform\Attachments\Domain\AttachmentOwnerType;
use App\Modules\Platform\Exceptions\ProblemException;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Enum\DeliveryState;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Events\AgentReplyPosted;
use App\Modules\Tickets\Domain\Events\CustomerReplyPosted;
use App\Modules\Tickets\Domain\History\TicketEventKind;
use App\Modules\Tickets\Domain\History\TicketEventRecorder;
use App\Modules\Tickets\Domain\Ticket;
use App\Modules\Tickets\Domain\TicketMessage;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * Adds a message to a ticket.
 *
 * TAKES NO VERSION, ON PURPOSE.
 *
 * The version guard exists for the five properties two people genuinely contend
 * over — where a second write silently reverts the first. Two people writing
 * different replies are not in that situation: they have both said something,
 * and both belong in the thread. Requiring a version here would make a reply
 * fail whenever a colleague happened to change the priority a moment earlier,
 * which on a busy ticket is most of the time, and would train people to retry
 * blindly until it went through.
 *
 * It also does not touch `version` or lock the ticket row. An append is not a
 * change to the ticket's contended state, and bumping the version would make
 * everyone else's open edit form stale for no reason.
 */
final class AppendMessage
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TicketEventRecorder $history,
    ) {}

    /**
     * @param  list<string>  $attachmentIds  Already uploaded and scanned; this
     *                                       claims them for the message.
     */
    public function handle(
        Actor $actor,
        string $ticketId,
        MessageDirection $direction,
        string $body,
        array $attachmentIds = [],
    ): TicketMessage {
        if (trim($body) === '') {
            throw ProblemException::make(
                'tickets.message_empty',
                'Write something before sending',
                422,
                'A message needs a body.',
            );
        }

        $message = $this->db->transaction(function () use ($actor, $ticketId, $direction, $body, $attachmentIds): TicketMessage {
            // Read, not locked: nothing here contends with anything.
            $ticket = Ticket::query()->whereKey($ticketId)->first();

            if ($ticket === null) {
                throw ProblemException::make(
                    'tickets.not_found',
                    'Ticket not found',
                    404,
                    "No ticket with id [{$ticketId}].",
                );
            }

            $message = new TicketMessage;

            $message->forceFill([
                'ticket_id' => $ticket->getKey(),
                // Denormalised from the ticket so the customer's whole
                // interaction history is one indexed read.
                'customer_id' => $ticket->customer_id,
                'direction' => $direction->value,
                'author_type' => $actor->kind(),
                'author_id' => $actor->id(),
                'author_name' => $actor->label(),
                'body' => $body,
                'sent_at' => now(),
                /*
                 * Only outbound messages have a delivery state. An inbound
                 * message arrived by definition and an internal note is never
                 * sent anywhere — claiming either is "queued" would put both
                 * in a waiting state they will never leave.
                 */
                'delivery_state' => $direction === MessageDirection::Outbound
                    ? DeliveryState::Queued->value
                    : null,
            ])->save();

            $this->claimAttachments($message, (string) $ticket->getKey(), $attachmentIds);

            /*
             * The history entry, inside the same transaction as the message.
             * These are the kinds Story 4.3 reserved with a TODO pointing here.
             */
            $this->history->record(
                (string) $ticket->getKey(),
                match ($direction) {
                    MessageDirection::Inbound => TicketEventKind::MessageReceived,
                    MessageDirection::Outbound => TicketEventKind::MessageSent,
                    MessageDirection::Internal => TicketEventKind::NoteAdded,
                },
                $actor,
                before: null,
                after: null,
                // The body is NOT copied here. It already lives on the message,
                // and duplicating it into an append-only store means a message
                // corrected at source and a copy that can never be.
                meta: [
                    'message_id' => (string) $message->getKey(),
                    'attachment_count' => count($attachmentIds),
                ],
                versionAfter: $ticket->version,
            );

            if ($attachmentIds !== []) {
                $this->history->record(
                    (string) $ticket->getKey(),
                    TicketEventKind::AttachmentAdded,
                    $actor,
                    before: null,
                    after: null,
                    meta: [
                        'message_id' => (string) $message->getKey(),
                        'attachment_ids' => array_values($attachmentIds),
                    ],
                    versionAfter: $ticket->version,
                );
            }

            return $message;
        });

        /*
         * Announced after the transaction, and ONLY for an outbound message.
         *
         * The direction check lives here, at the dispatch, rather than in
         * whatever listens: an internal note is a colleague's private remark
         * about the customer it would otherwise be emailed to, and that is the
         * one failure in this area that cannot be taken back. A listener that
         * forgot to check would be one file away from causing it; an event that
         * is never fired cannot.
         */
        if ($direction === MessageDirection::Outbound) {
            Event::dispatch(new AgentReplyPosted($ticketId, (string) $message->getKey()));
        }

        if ($direction === MessageDirection::Inbound) {
            /*
             * Announced here rather than by whoever received the message.
             *
             * A customer reply has consequences beyond the thread — a Pending
             * ticket becomes Open again, and the auto-close sweep stops
             * counting silence. Those rules live in ReopenOnCustomerReply, and
             * they must apply whether the reply arrived by email, through the
             * portal, or by an agent logging a phone call. Dispatching at each
             * of those call sites would mean three places to remember, and the
             * one that forgets leaves a customer waiting on a ticket that has
             * dropped out of every queue.
             */
            Event::dispatch(new CustomerReplyPosted($ticketId, (string) $message->getKey()));
        }

        return $message;
    }

    /**
     * Moves already-uploaded attachments from the ticket onto this message.
     *
     * Upload happens first and separately, so a slow or refused file never
     * costs the agent the reply they typed. Until the message exists the files
     * belong to the TICKET — `owner_id` is not nullable, and an owner-less
     * attachment would be a row nothing can be reached from or cleaned up by.
     * Sending re-points them at the message that now carries them.
     *
     * Only files already owned by THIS ticket move. Otherwise a caller could
     * name any id and pull a stranger's file into their own conversation.
     *
     * @param  list<string>  $attachmentIds
     */
    private function claimAttachments(TicketMessage $message, string $ticketId, array $attachmentIds): void
    {
        if ($attachmentIds === []) {
            return;
        }

        $moved = DB::table('attachments')
            ->whereIn('id', $attachmentIds)
            ->where('owner_type', AttachmentOwnerType::Ticket->value)
            ->where('owner_id', $ticketId)
            ->update([
                'owner_type' => AttachmentOwnerType::Message->value,
                'owner_id' => $message->getKey(),
            ]);

        if ($moved !== count(array_unique($attachmentIds))) {
            /*
             * All or nothing. A reply that silently went out with two of the
             * three files the agent attached is worse than one that failed:
             * they would never know to send the third.
             */
            throw ProblemException::make(
                'tickets.attachment_unavailable',
                'An attachment could not be added',
                422,
                'One or more attachments do not exist on this ticket, or are already attached to a message.',
            );
        }
    }
}
