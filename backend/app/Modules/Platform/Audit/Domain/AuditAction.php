<?php

declare(strict_types=1);

namespace App\Modules\Platform\Audit\Domain;

/**
 * What was done.
 *
 * A closed set, not a free string. Every value here is a filter option in the
 * console and a row in someone's incident timeline — an open vocabulary would
 * mean `user.updated`, `user.update` and `users.updated` all existing, and a
 * filter that quietly misses two thirds of what happened.
 *
 * `{subject}.{past tense verb}` throughout. Past tense because an audit entry
 * records something that already happened; a name like `user.update` reads as
 * an intention, and intentions are not what this table stores.
 */
enum AuditAction: string
{
    case SignInSucceeded = 'auth.sign_in.succeeded';
    case SignInFailed = 'auth.sign_in.failed';

    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserDeactivated = 'user.deactivated';
    case UserReactivated = 'user.reactivated';

    case DepartmentCreated = 'department.created';
    case DepartmentUpdated = 'department.updated';
    case DepartmentDeleted = 'department.deleted';

    case ConfigChanged = 'config.changed';

    /*
     * Publishing and archiving are recorded because they change who can see an
     * answer, and deleting because it removes one. Editing a draft is not
     * audited: a draft nobody has seen is a document somebody is still writing,
     * and recording every keystroke-sized save would bury the three events that
     * matter.
     */
    case ArticlePublished = 'article.published';
    case ArticleArchived = 'article.archived';
    case ArticleDeleted = 'article.deleted';

    case TicketFieldChanged = 'ticket.field_changed';
    case CustomerFieldChanged = 'customer.field_changed';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
