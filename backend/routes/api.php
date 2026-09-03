<?php

declare(strict_types=1);

use App\Modules\Platform\Http\Controllers\Admin\QuickRepliesController;
use App\Modules\Customers\Http\Controllers\CustomerDuplicatesController;
use App\Modules\Customers\Http\Controllers\CustomerNotesController;
use App\Modules\Customers\Http\Controllers\CustomersController;
use App\Modules\Platform\Attachments\Http\Controllers\AttachmentsController;
use App\Modules\Platform\Audit\Http\Controllers\AuditEntriesController;
use App\Modules\Platform\Http\Controllers\Admin\SettingsController;
use App\Modules\Platform\Http\Controllers\HealthController;
use App\Modules\Platform\Http\Middleware\IdempotencyKey;
use App\Modules\Security\Domain\Capabilities;
use App\Modules\Security\Http\Controllers\AuthController;
use App\Modules\Security\Http\Controllers\DepartmentsController;
use App\Modules\Security\Http\Controllers\PasswordResetController;
use App\Modules\Security\Http\Controllers\ProfileController;
use App\Modules\Security\Http\Controllers\UsersController;
use App\Modules\Tickets\Domain\Category;
use App\Modules\Tickets\Http\Controllers\Admin\CategoriesController;
use App\Modules\Tickets\Http\Controllers\CustomerTimelineController;
use App\Modules\Tickets\Http\Controllers\PortalTicketsController;
use App\Modules\Tickets\Http\Controllers\CustomerContextController;
use App\Modules\Tickets\Http\Controllers\TicketEventsController;
use App\Modules\Tickets\Http\Controllers\TicketMessagesController;
use App\Modules\Tickets\Http\Controllers\TicketsController;
use App\Modules\Tickets\Http\Controllers\Admin\PrioritiesController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

/*
 * Every API route lives under /api/v1. The version segment is part of the
 * contract the generated TypeScript client is built from, so it is declared
 * once here rather than repeated per route.
 *
 * Authentication is Sanctum in SPA cookie mode: statefulApi() is registered in
 * bootstrap/app.php, so requests from the configured frontend origin carry the
 * session cookie. No token is ever issued.
 */
Route::prefix('v1')->group(function (): void {
    Route::get('/healthz', [HealthController::class, 'show'])->name('platform.healthz');

    // Temporary write endpoint; see HealthController::echo().
    Route::post('/healthz-echo', [HealthController::class, 'echo'])->name('platform.healthz-echo');

    /*
     * ---------------------------------------------------------------------
     * Session operations — exempt from Idempotency-Key
     * ---------------------------------------------------------------------
     *
     * That middleware exists to stop a retried WRITE from creating a second
     * record. A session operation creates none, and a replayed sign-in response
     * cannot carry a Set-Cookie, which would make the replay actively wrong.
     * Requiring a key here would break every ordinary form post and curl for no
     * safety gain.
     *
     * The scope of this exemption is deliberately narrow — see the
     * administration group below, which does NOT get it.
     */
    Route::withoutMiddleware(IdempotencyKey::class)->group(function (): void {
        Route::post('/auth/login', [AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('auth.login');

        Route::post('/auth/password/forgot', [PasswordResetController::class, 'sendResetLink'])
            ->middleware('throttle:password-reset')
            ->name('auth.password.forgot');

        Route::post('/auth/password/reset', [PasswordResetController::class, 'reset'])
            // A separate budget from requesting a link — see the limiter.
            ->middleware('throttle:password-reset-confirm')
            ->name('auth.password.reset');

        Route::get('/auth/session', [AuthController::class, 'session'])->name('auth.session');

        Route::middleware('auth:web')->group(function (): void {
            Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');

            Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
            Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
            Route::post('/profile/password', [ProfileController::class, 'changePassword'])
                ->name('profile.password');
        });
    });

    /*
     * ---------------------------------------------------------------------
     * Administration
     * ---------------------------------------------------------------------
     *
     * Every route carries an explicit capability. The middleware is the
     * enforcement — hiding a control in the UI is a suggestion, not a refusal,
     * and these endpoints are reachable by curl regardless.
     *
     * These are resource writes, so they DO honour Idempotency-Key: creating a
     * user twice because a request was retried is exactly the failure that
     * middleware prevents.
     */
    Route::middleware('auth:web')->group(function (): void {
        Route::middleware('can.capability:'.Capabilities::USER_MANAGE)->group(function (): void {
            Route::get('/users', [UsersController::class, 'index'])->name('users.index');
            Route::post('/users', [UsersController::class, 'store'])->name('users.store');
            Route::get('/users/{user}', [UsersController::class, 'show'])->name('users.show');
            Route::patch('/users/{user}', [UsersController::class, 'update'])->name('users.update');
            Route::post('/users/{user}/deactivate', [UsersController::class, 'deactivate'])
                ->name('users.deactivate');
        });

        /*
         * Reading the department list is separated from changing it. Every
         * staff member needs the list — it fills the customer filter and the
         * customer form's picker — and none of them may edit it.
         */
        Route::middleware('can.capability:'.Capabilities::DEPARTMENT_READ)->group(function (): void {
            Route::get('/departments', [DepartmentsController::class, 'index'])->name('departments.index');
        });

        Route::middleware('can.capability:'.Capabilities::DEPARTMENT_MANAGE)->group(function (): void {
            Route::post('/departments', [DepartmentsController::class, 'store'])->name('departments.store');
            Route::patch('/departments/{department}', [DepartmentsController::class, 'update'])
                ->name('departments.update');
            Route::post('/departments/{department}/deactivate', [DepartmentsController::class, 'deactivate'])
                ->name('departments.deactivate');
        });

        /*
         * Capability probes for surfaces whose real handlers arrive later.
         *
         * They exist so the refusal behaviour is enforced and TESTED from this
         * story onward rather than retrofitted — the guard is the deliverable
         * here, not the handler behind it.
         *
         * TODO(Story 2.4): replace with the audit log reader.
         * TODO(Story 2.3): replace with the settings surface.
         * TODO(Story 4.1): replace with the real ticket reassignment.
         */
        Route::get('/audit', fn () => new JsonResponse(['data' => []]))
            ->middleware('can.capability:'.Capabilities::AUDIT_READ)
            ->name('audit.index');

        Route::put('/settings/{key}', fn (string $key) => new JsonResponse(['key' => $key]))
            ->middleware('can.capability:'.Capabilities::SETTING_MANAGE)
            ->name('settings.update');

        /*
         * Tickets.
         *
         * Each verb carries the capability that matches what it actually does,
         * rather than one blanket `tickets.write`: reassigning work is a
         * supervisor's job, while creating and updating are an agent's, and a
         * single capability would have to be the loosest of them.
         *
         * Every write also passes the Idempotency-Key middleware the api group
         * applies, so a retried create cannot produce two tickets.
         */
        Route::prefix('tickets')->name('tickets.')->group(function (): void {
            /*
             * The list and the counts strip. Read-only, so no Idempotency-Key.
             *
             * `counts` is registered BEFORE `/{ticket}`: otherwise the ULID
             * pattern would have to be the only thing stopping the router from
             * treating the literal word "counts" as a ticket id, and a
             * constraint is a worse guarantee than an order.
             */
            Route::get('/', [TicketsController::class, 'index'])
                ->middleware('can.capability:'.Capabilities::TICKET_READ)
                ->name('index');

            Route::get('/counts', [TicketsController::class, 'counts'])
                ->middleware('can.capability:'.Capabilities::TICKET_READ)
                ->name('counts');

            Route::post('/', [TicketsController::class, 'store'])
                ->middleware('can.capability:'.Capabilities::TICKET_CREATE)
                ->name('store');

            Route::get('/{ticket}', [TicketsController::class, 'show'])
                ->middleware('can.capability:'.Capabilities::TICKET_READ)
                ->whereUlid('ticket')
                ->name('show');

            Route::patch('/{ticket}', [TicketsController::class, 'updateAttributes'])
                ->middleware('can.capability:'.Capabilities::TICKET_CHANGE_STATUS)
                ->whereUlid('ticket')
                ->name('update');

            Route::post('/{ticket}/assign', [TicketsController::class, 'assign'])
                // Assigning, not reassigning: taking work off a colleague is a
                // second check inside the command, on TICKET_REASSIGN_ANY.
                ->middleware('can.capability:'.Capabilities::TICKET_ASSIGN)
                ->whereUlid('ticket')
                ->name('assign');

            Route::post('/{ticket}/resolve', [TicketsController::class, 'resolveTicket'])
                ->middleware('can.capability:'.Capabilities::TICKET_RESOLVE)
                ->whereUlid('ticket')
                ->name('resolve');

            Route::patch('/{ticket}/department', [TicketsController::class, 'changeDepartment'])
                ->middleware('can.capability:'.Capabilities::TICKET_CHANGE_DEPARTMENT)
                ->whereUlid('ticket')
                ->name('department');

            Route::post('/{ticket}/reopen', [TicketsController::class, 'reopenTicket'])
                ->middleware('can.capability:'.Capabilities::TICKET_REOPEN)
                ->whereUlid('ticket')
                ->name('reopen');

            /*
             * Replying. Note there is no `version` on this route: an append is
             * not a change to the ticket's contended state, so two colleagues
             * replying at once are not in conflict. See AppendMessage.
             */
            Route::get('/{ticket}/messages', [TicketMessagesController::class, 'index'])
                ->middleware('can.capability:'.Capabilities::TICKET_READ)
                ->whereUlid('ticket')
                ->name('messages.index');

            Route::post('/{ticket}/messages', [TicketMessagesController::class, 'store'])
                ->middleware('can.capability:'.Capabilities::TICKET_UPDATE)
                ->whereUlid('ticket')
                ->name('messages.store');

            /*
             * History. GET and nothing else — deliberately no POST, PATCH, PUT
             * or DELETE on this URI or below it. `TicketEventsAppendOnlyTest`
             * fails if one is ever added, because a route is the easiest of the
             * three layers to open by accident.
             */
            Route::get('/{ticket}/events', [TicketEventsController::class, 'index'])
                ->middleware('can.capability:'.Capabilities::TICKET_READ)
                ->whereUlid('ticket')
                ->name('events.index');

            Route::post('/{ticket}/messages/{message}/retry', [TicketMessagesController::class, 'retry'])
                ->middleware('can.capability:'.Capabilities::TICKET_UPDATE)
                ->whereUlid('ticket')
                ->whereUlid('message')
                ->name('messages.retry');

            /*
             * Who this ticket is for, and what else they have open.
             *
             * Gated on reading the TICKET, not the customer record: an agent
             * answering a request can already see who sent it. It returns
             * counts and a name, not the customer's full file — that stays
             * behind `customer.read` at its own endpoint.
             */
            Route::get('/{ticket}/customer-context', [CustomerContextController::class, 'show'])
                ->middleware('can.capability:'.Capabilities::TICKET_READ)
                ->whereUlid('ticket')
                ->name('customer-context');
        });

        /*
         * -----------------------------------------------------------------
         * Configuration console
         * -----------------------------------------------------------------
         *
         * Gated on `setting.manage`, the capability Story 1.3 already defines
         * and tests, rather than a bespoke `role:administrator` alias. The plan
         * proposed the latter as a stub; the capability gate exists, is
         * enforced identically, and keeps one answer to "who may configure
         * this?".
         */
        Route::middleware('can.capability:'.Capabilities::SETTING_MANAGE)
            ->prefix('admin')
            ->name('admin.')
            ->group(function (): void {
                Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
                Route::patch('/settings/{key}', [SettingsController::class, 'update'])
                    ->where('key', '[A-Za-z0-9_.]+')
                    ->name('settings.update');

                Route::get('/quick-replies', [QuickRepliesController::class, 'index'])->name('quick-replies.index');
                Route::post('/quick-replies', [QuickRepliesController::class, 'store'])->name('quick-replies.store');
                Route::post('/quick-replies/reorder', [QuickRepliesController::class, 'reorder'])
                    ->name('quick-replies.reorder');
                Route::patch('/quick-replies/{id}', [QuickRepliesController::class, 'update'])->name('quick-replies.update');
                Route::delete('/quick-replies/{id}', [QuickRepliesController::class, 'destroy'])->name('quick-replies.destroy');

                Route::get('/categories', [CategoriesController::class, 'index'])->name('categories.index');
                Route::post('/categories', [CategoriesController::class, 'store'])->name('categories.store');
                Route::patch('/categories/{category}', [CategoriesController::class, 'update'])->name('categories.update');
                Route::delete('/categories/{category}', [CategoriesController::class, 'destroy'])->name('categories.destroy');

                Route::get('/priorities', [PrioritiesController::class, 'index'])->name('priorities.index');

                /*
                 * TODO(Story 5.1): actually send. 202 states the request was
                 * accepted, which is honest — nothing has been delivered yet.
                 */
                Route::post('/email/test', fn () => new JsonResponse(['status' => 'accepted'], 202))
                    ->name('email.test');
            });

        /*
         * Customers.
         *
         * Reading and writing are separate capabilities: an agent looks people
         * up all day and only a supervisor corrects their details.
         *
         * The duplicate preview is a READ — it reports who already exists and
         * changes nothing — so it sits behind customer.read even though it is a
         * POST. The verb is POST because the identifiers being checked belong
         * in a body, not in a query string that lands in access logs.
         */
        Route::middleware('can.capability:'.Capabilities::CUSTOMER_READ)
            ->prefix('customers')
            ->name('customers.')
            ->group(function (): void {
                Route::get('/', [CustomersController::class, 'index'])->name('index');
                /*
                 * Exempt from the Idempotency-Key requirement. It is a POST
                 * only because the identifiers being checked belong in a body
                 * rather than in a query string that lands in access logs — it
                 * creates nothing, so a retry has nothing to duplicate and a
                 * replayed answer would be staler than a fresh one.
                 */
                Route::post('/duplicates/preview', [CustomerDuplicatesController::class, 'preview'])
                    ->withoutMiddleware(IdempotencyKey::class)
                    ->name('duplicates.preview');
                Route::get('/{id}', [CustomersController::class, 'show'])
                    ->whereUlid('id')->name('show');

                /*
                 * The interaction timeline. Read-only, so no Idempotency-Key.
                 *
                 * The path is keyed by customer because that is how a person
                 * thinks about it; the HANDLER lives in Tickets, which owns the
                 * rows. Tickets (T3) may depend on Customers (T2) — downward —
                 * while the reverse would invert the tier graph.
                 */
                Route::get('/{customer}/timeline', [CustomerTimelineController::class, 'index'])
                    ->whereUlid('customer')->name('timeline');
            });

        Route::middleware('can.capability:'.Capabilities::CUSTOMER_MANAGE)
            ->prefix('customers')
            ->name('customers.')
            ->group(function (): void {
                Route::post('/', [CustomersController::class, 'store'])->name('store');
                Route::patch('/{id}', [CustomersController::class, 'update'])
                    ->whereUlid('id')->name('update');
                Route::post('/{id}/deactivate', [CustomersController::class, 'deactivate'])
                    ->whereUlid('id')->name('deactivate');
                Route::post('/{id}/reactivate', [CustomersController::class, 'reactivate'])
                    ->whereUlid('id')->name('reactivate');
            });

        /*
         * Notes on a customer.
         *
         * Reading and writing need only customer READ: recording what a caller
         * said is part of handling the call. Who may EDIT or DELETE one is
         * about authorship, not role, so it is decided in the controller
         * rather than by a capability on the route.
         */
        Route::middleware('can.capability:'.Capabilities::CUSTOMER_READ)
            ->name('customers.notes.')
            ->group(function (): void {
                Route::get('/customers/{customer}/notes', [CustomerNotesController::class, 'index'])
                    ->whereUlid('customer')->name('index');
                Route::post('/customers/{customer}/notes', [CustomerNotesController::class, 'store'])
                    ->whereUlid('customer')->name('store');
                Route::patch('/notes/{note}', [CustomerNotesController::class, 'update'])
                    ->whereUlid('note')->name('update');
                Route::delete('/notes/{note}', [CustomerNotesController::class, 'destroy'])
                    ->whereUlid('note')->name('destroy');
            });

        /*
         * Attachments.
         *
         * Gated on being signed in and nothing narrower: the three owner kinds
         * have different audiences, and a capability here would have to be the
         * loosest of them, which protects nothing. The owning module decides
         * who may attach to what when it builds the upload.
         *
         * There is no inline-preview route, and there will not be one. Serving
         * uploaded content inline from a trusted origin is a stored XSS that no
         * virus scanner would flag.
         */
        Route::prefix('attachments')->name('attachments.')->group(function (): void {
            Route::get('/', [AttachmentsController::class, 'index'])->name('index');
            Route::post('/', [AttachmentsController::class, 'store'])->name('store');
            Route::get('/{id}', [AttachmentsController::class, 'show'])
                ->whereUlid('id')->name('show');
            Route::get('/{id}/download', [AttachmentsController::class, 'download'])
                ->whereUlid('id')->name('download');
        });

        /*
         * Quick replies, for the people who actually send them.
         *
         * The admin block above owns writing them — that is configuration. This
         * is the read an agent's composer makes, gated on being allowed to
         * reply rather than on being allowed to administer settings. Without
         * it the picker would be empty for everyone except administrators,
         * which is everyone who uses it.
         */
        Route::get('/quick-replies', [QuickRepliesController::class, 'index'])
            ->middleware('can.capability:'.Capabilities::TICKET_UPDATE)
            ->name('quick-replies.index');

        /*
         * The audit log: two GETs, and deliberately nothing else.
         *
         * No `apiResource` here. That helper registers five routes, two of
         * which mutate — and the whole point of this table is that no HTTP
         * path can. With only GET registered the router answers 405 to a PUT,
         * PATCH or DELETE, which is a stronger guarantee than a controller
         * method that chooses to refuse.
         *
         * Gated on `audit.read`, which by the role matrix only an
         * administrator holds.
         */
        Route::middleware('can.capability:'.Capabilities::AUDIT_READ)
            ->prefix('audit-entries')
            ->name('audit-entries.')
            ->group(function (): void {
                Route::get('/', [AuditEntriesController::class, 'index'])->name('index');
                Route::get('/{id}', [AuditEntriesController::class, 'show'])
                    ->whereUlid('id')
                    ->name('show');
            });
    });

    /*
     * The customer-facing surface.
     *
     * A separate guard, not a role on the staff one: staff and portal customers
     * are different populations in different tables, and a single guard would
     * make "which kind of person is this?" a runtime question every endpoint had
     * to get right.
     *
     * No capability middleware. A portal account holds no roles; what it may
     * reach is decided by which routes live in this group, and each one scopes
     * to the account's own customer.
     */
    Route::middleware('auth:portal')->prefix('portal')->name('portal.')->group(function (): void {
        Route::get('/tickets', [PortalTicketsController::class, 'index'])->name('tickets.index');
        Route::post('/tickets', [PortalTicketsController::class, 'store'])->name('tickets.store');
    });
});
