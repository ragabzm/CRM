<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\History;

/**
 * Every kind of thing that can happen to a ticket.
 *
 * An enum rather than loose strings so a typo is a fatal error instead of a row
 * nobody can find again — a history you cannot filter is a history you cannot
 * read.
 *
 * The backing values keep the `ticket.` prefix the module has written since
 * Story 4.1. Renaming them to bare snake_case would have meant rewriting rows
 * that already exist, and the point of an append-only store is that its rows do
 * not get rewritten.
 */
enum TicketEventKind: string
{
    case Created = 'ticket.created';

    case StatusChanged = 'ticket.status_changed';

    /**
     * Narrower forms of StatusChanged.
     *
     * Kept distinct because "resolved" and "reopened" are the two moments a
     * reader actually looks for in a long history, and finding them means
     * filtering rather than reading every status change and comparing values.
     */
    case Resolved = 'ticket.resolved';

    case Reopened = 'ticket.reopened';

    case AssigneeChanged = 'ticket.assignee_changed';

    case PriorityChanged = 'ticket.priority_changed';

    case CategoryChanged = 'ticket.category_changed';

    case DepartmentChanged = 'ticket.department_changed';

    case MessageSent = 'ticket.message_sent';

    case MessageReceived = 'ticket.message_received';

    // TODO(Story 4.4 / #506): the internal-notes write path emits this.
    case NoteAdded = 'ticket.note_added';

    // TODO(Story 4.4 / #506): the ticket attachment write path emits this.
    case AttachmentAdded = 'ticket.attachment_added';

    // TODO(Story 5.3 / #510): the SLA breach detector emits this through
    // TicketEventRecording, so Sla never touches the Tickets model.
    case SlaBreached = 'ticket.sla_breached';

    /**
     * Whether this kind is written by a path that exists today.
     *
     * Used by the architecture test that would otherwise have no way to tell a
     * kind nobody emits yet from a kind somebody forgot to wire up.
     */
    public function isEmittedYet(): bool
    {
        return ! in_array($this, [self::NoteAdded, self::AttachmentAdded, self::SlaBreached], true);
    }
}
