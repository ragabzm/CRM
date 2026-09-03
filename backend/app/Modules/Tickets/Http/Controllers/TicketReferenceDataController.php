<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Tickets\Domain\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The two lists the ticket workspace needs to render its selects.
 *
 * They did not exist. `TicketDetailPage` has been asking for
 * `/ticket-categories` and `/assignees` since the workspace shipped, both
 * returned 404, and the page swallows a reference-data failure on purpose — so
 * the Category and Assignee selects were permanently empty, showing a blank
 * where the ticket's own category and assignee should be. An agent could not
 * see who held a ticket, or move it.
 *
 * Nothing caught it because the swallow is deliberate and correct: a select
 * with no options is a smaller problem than a workspace that will not open.
 * What was missing is anything that noticed the options were ALWAYS empty.
 *
 * Deliberately not `/admin/categories` and `/users`. Both are gated on
 * capabilities an ordinary agent does not hold — an agent who cannot manage
 * the category list still has to be able to pick from it. These are read-only,
 * narrow, and gated on reading a ticket.
 */
final class TicketReferenceDataController extends Controller
{
    /**
     * @response array{data: list<array{id:int,name:string}>}
     */
    public function categories(Request $request): JsonResponse
    {
        /*
         * One name, already in the reader's language. The admin endpoint
         * returns both columns because an administrator is editing them; a
         * select is not, and handing it both would make every caller decide
         * which to show — with English as the accident when they forgot.
         */
        $column = $request->user()?->preferredLocale() === 'ar' ? 'name_ar' : 'name_en';

        $categories = Category::query()
            ->orderBy('sort_order')
            ->orderBy('name_en')
            ->get()
            ->map(static fn (Category $category): array => [
                'id' => (int) $category->getKey(),
                'name' => (string) $category->{$column},
            ])
            ->all();

        return new JsonResponse(['data' => $categories]);
    }

    /**
     * Who can be handed a ticket.
     *
     * @response array{data: list<array{id:int,name:string}>}
     */
    public function assignees(): JsonResponse
    {
        $people = User::query()
            /*
             * Active accounts only. Offering a deactivated colleague in the
             * picker invites an agent to park work somewhere nobody is
             * looking — and `AssignTicket` would refuse it anyway, so the
             * option could only ever produce an error.
             */
            ->where('is_active', true)
            /*
             * Staff, not customers. The `customer` role exists in the same
             * table for portal-era reasons, and a customer in the assignee
             * list would be somebody the desk could hand its own work to.
             */
            ->whereHas('roles', static fn ($query) => $query->where('name', '!=', 'customer'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (User $user): array => [
                'id' => (int) $user->getKey(),
                'name' => (string) $user->name,
            ])
            ->all();

        return new JsonResponse(['data' => $people]);
    }
}
