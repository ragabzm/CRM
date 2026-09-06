<?php

declare(strict_types=1);

use App\Modules\Channels\Http\Controllers\Admin\ChannelAccountsController;
use App\Modules\Knowledge\Http\Controllers\Admin\ArticleCategoriesController;
use App\Modules\Knowledge\Http\Controllers\ArticleSearchController;
use App\Modules\Knowledge\Http\Controllers\HelpCentreController;
use App\Modules\Knowledge\Http\Controllers\Admin\ArticleLifecycleController;
use App\Modules\Knowledge\Http\Controllers\Admin\ArticlesController;
use App\Modules\Knowledge\Http\Controllers\Admin\ArticleTranslationsController;
use App\Modules\Assist\Http\Controllers\TicketAssistController;
use App\Modules\Channels\Http\Controllers\ChatDeskController;
use App\Modules\Channels\Http\Controllers\ChatWidgetController;
use App\Modules\Channels\Http\Controllers\PhoneWebhookController;
use App\Modules\Channels\Http\Controllers\WebFormAttachmentController;
use App\Modules\Channels\Http\Controllers\WebFormIntakeController;
use App\Modules\Email\Http\Controllers\EmailTestSendController;
use App\Modules\Email\Http\Controllers\InboundWebhookController;
use App\Modules\Email\Http\Controllers\MailLogController;
use App\Modules\Email\Http\Controllers\MailQuarantineController;
use App\Modules\Integrations\Http\Controllers\IntegrationsController;
use App\Modules\Platform\Branches\Http\Controllers\BranchesController;
use App\Modules\Platform\Branding\Http\Controllers\BrandingController;
use App\Modules\Platform\Http\Controllers\Admin\QuickRepliesController;
use App\Modules\Reporting\Http\Controllers\ReportsController;
use App\Modules\Sla\Http\Controllers\SlaPreviewController;
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
use App\Modules\Security\Http\Controllers\Admin\ApiClientsController;
use App\Modules\Security\Http\Middleware\RefuseCredentialsInTheUrl;
use App\Modules\Security\Http\Controllers\UsersController;
use App\Modules\Tickets\Domain\Category;
use App\Modules\Tickets\Http\Controllers\Admin\AssignmentMappingsController;
use App\Modules\Tickets\Http\Controllers\Admin\CategoriesController;
use App\Modules\Tickets\Http\Controllers\CustomerTimelineController;
use App\Modules\Tickets\Http\Controllers\TicketEscalationController;
use App\Modules\Portal\Http\Controllers\PortalAuthController;
use App\Modules\Portal\Http\Controllers\PortalPasswordController;
use App\Modules\Portal\Http\Controllers\PortalRequestsController;
use App\Modules\Tickets\Http\Controllers\CustomerContextController;
use App\Modules\Tickets\Http\Controllers\NotificationsController;
use App\Modules\Tickets\Http\Controllers\TicketEventsController;
use App\Modules\Tickets\Http\Controllers\TicketMessagesController;
use App\Modules\Tickets\Http\Controllers\TicketReferenceDataController;
use App\Modules\Tickets\Http\Controllers\FeedbackInvitationController;
use App\Modules\Tickets\Http\Controllers\PersonalWorkController;
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

        /*
     * The session cookie OR a bearer token. ONE group, not two.
     *
     * This is the whole claim of the story made structural: there is no second
     * API and no internal-only endpoint, because an API client and the
     * interface enter through the same door and reach the same controller,
     * the same validation, the same capability gate and the same command.
     *
     * `auth:web,sanctum` tries the cookie first — the interface is the common
     * case — and falls back to the bearer header. `RequireCapability` then
     * ANDs the person's capability with the token's ability, so a token
     * narrows and never widens.
     */
    Route::middleware(['auth:web,sanctum', RefuseCredentialsInTheUrl::class, 'throttle:api-client'])->group(function (): void {
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
    /*
     * The session cookie OR a bearer token. ONE group, not two.
     *
     * This is the whole claim of the story made structural: there is no second
     * API and no internal-only endpoint, because an API client and the
     * interface enter through the same door and reach the same controller,
     * the same validation, the same capability gate and the same command.
     *
     * `auth:web,sanctum` tries the cookie first — the interface is the common
     * case — and falls back to the bearer header. `RequireCapability` then
     * ANDs the person's capability with the token's ability, so a token
     * narrows and never widens.
     */
    Route::middleware(['auth:web,sanctum', RefuseCredentialsInTheUrl::class, 'throttle:api-client'])->group(function (): void {
        /*
         * Branches: read them to pick and filter, manage them to change the
         * org chart. Two capabilities, because an agent who cannot administer
         * the list still has to be able to see it — a label nobody can read is
         * not a label.
         *
         * There is no delete route. A branch that closed still describes where
         * years of tickets happened; deactivation is the only way out.
         */
        /*
         * The other systems that talk to this one.
         *
         * Administrator-only, beside users and branches: issuing a credential
         * that can read every customer is not a supervisor's decision about
         * their own team.
         */
        /*
         * The integrations surface: the exchange log, and the button that
         * tests a configuration by making a real exchange.
         *
         * Gated on `setting.manage`, because that is what configuring an
         * integration IS — and there is no delete route on the log, because
         * retention is the only way a row ever leaves it.
         */
        Route::middleware('can.capability:'.Capabilities::SETTING_MANAGE)->group(function (): void {
            Route::get('/integrations/log', [IntegrationsController::class, 'log'])->name('integrations.log');
            Route::post('/integrations/erp/test', [IntegrationsController::class, 'test'])->name('integrations.test');
        });

        Route::middleware('can.capability:'.Capabilities::USER_MANAGE)->group(function (): void {
            Route::get('/api-clients', [ApiClientsController::class, 'index'])->name('api-clients.index');
            Route::post('/api-clients', [ApiClientsController::class, 'store'])->name('api-clients.store');

            Route::delete('/api-clients/{client}', [ApiClientsController::class, 'destroy'])
                ->whereNumber('client')
                ->name('api-clients.destroy');
        });

        Route::get('/branches', [BranchesController::class, 'index'])
            ->middleware('can.capability:'.Capabilities::BRANCH_READ)
            ->name('branches.index');

        Route::middleware('can.capability:'.Capabilities::BRANCH_MANAGE)->group(function (): void {
            Route::post('/branches', [BranchesController::class, 'store'])->name('branches.store');

            Route::patch('/branches/{branch}', [BranchesController::class, 'update'])
                ->whereNumber('branch')
                ->name('branches.update');
        });

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
        /*
         * The bell. No capability gate: these are the signed-in person's own
         * notifications, and holding any staff role is exactly the permission
         * needed to read what was sent to you.
         */
        Route::get('/notifications', [NotificationsController::class, 'index'])
            ->name('notifications.index');

        Route::post('/notifications/{id}/read', [NotificationsController::class, 'markRead'])
            ->name('notifications.read');

        /*
         * An agent's own tasks, reminders and mentions.
         *
         * `/me/…`, and no capability gate — for the same reason the bell has
         * none. These are the notes somebody writes to themselves, and an
         * administrator being able to switch off a colleague's to-do list is
         * not a permission anybody asked for.
         *
         * Under `/me` rather than a top-level `/tasks`: there is no global
         * task list and no destination in the sidebar, which is the settled IA
         * decision. The URL says whose these are, and there is no shape of
         * request that asks for anybody else's.
         */
        /*
         * The three in-ticket assists, plus the articles beside them.
         *
         * Gated on reading the ticket and nothing narrower: an agent who can
         * read a conversation can ask the machine about it, and the AI
         * capability switches decide whether there is anything to ask. Four
         * READS — there is no accept, apply or send route here, because
         * confirming a proposal is the ordinary command path with the
         * confirming person's name on it.
         */
        Route::middleware('can.capability:'.Capabilities::TICKET_READ)
            ->prefix('tickets/{ticket}/assist')
            ->whereUlid('ticket')
            ->name('assist.')
            ->group(function (): void {
                Route::get('/summary', [TicketAssistController::class, 'summary'])->name('summary');
                Route::get('/reply', [TicketAssistController::class, 'reply'])->name('reply');
                Route::get('/category', [TicketAssistController::class, 'category'])->name('category');
                Route::get('/articles', [TicketAssistController::class, 'articles'])->name('articles');
            });

        /*
         * The chat desk: who is waiting, and taking one of them.
         *
         * Gated on `chat.handle` rather than on reading a ticket, because
         * taking a chat commits you to being there NOW — which is a rota
         * decision a desk makes, not a consequence of being able to read the
         * queue. Replies are deliberately absent: an agent answers a chat
         * through the ticket's own message endpoint, like everything else.
         */
        Route::middleware('can.capability:'.Capabilities::CHAT_HANDLE)
            /*
             * `chat-desk`, deliberately not `chat`.
             *
             * The widget's own endpoints live under `/chat` and are reachable
             * with no session at all. Sharing a prefix between the two would
             * mean one path answering to two completely different
             * authorisations depending on the verb — which is exactly the
             * shape somebody reads too quickly and adds a public route to.
             */
            ->prefix('chat-desk')
            ->name('chat.desk.')
            ->group(function (): void {
                Route::get('/conversations', [ChatDeskController::class, 'index'])->name('index');

                Route::post('/conversations/{conversation}/take', [ChatDeskController::class, 'take'])
                    ->whereUlid('conversation')
                    ->name('take');

                Route::post('/conversations/{conversation}/close', [ChatDeskController::class, 'close'])
                    ->whereUlid('conversation')
                    ->name('close');
            });

        /*
         * The fixed report set. One endpoint, read-only, supervisor and above.
         *
         * The capability is the single gate — there is no report-specific
         * permission model, because the figures are about how a DESK is
         * performing rather than about a person. `RequireCapability` refuses
         * with a stated reason and a named person to ask, never an empty page.
         */
        Route::get('/reports', [ReportsController::class, 'show'])
            ->middleware('can.capability:'.Capabilities::REPORT_VIEW)
            ->name('reports.show');

        Route::prefix('me')->name('me.')->group(function (): void {
            Route::get('/tasks', [PersonalWorkController::class, 'tasks'])->name('tasks.index');
            Route::post('/tasks', [PersonalWorkController::class, 'storeTask'])->name('tasks.store');

            Route::patch('/tasks/{task}', [PersonalWorkController::class, 'updateTask'])
                ->whereUlid('task')
                ->name('tasks.update');

            Route::post('/reminders', [PersonalWorkController::class, 'storeReminder'])
                ->name('reminders.store');

            Route::get('/mentions', [PersonalWorkController::class, 'mentions'])->name('mentions.index');

            Route::post('/mentions/{mention}/read', [PersonalWorkController::class, 'readMention'])
                ->whereUlid('mention')
                ->name('mentions.read');
        });

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
             * Escalation: a POST to a named sub-resource, not a PATCH.
             *
             * A PATCH on the ticket carries `If-Match` and would be refused
             * when a colleague had touched the ticket first. Escalation must
             * never be refused for that reason — two people escalating the
             * same ticket agree, and the second is confirmation rather than
             * conflict. Its own capability for the same reason it is its own
             * endpoint: it is the one ticket action whose audience is other
             * people.
             */
            Route::post('/{ticket}/escalate', [TicketEscalationController::class, 'store'])
                ->middleware('can.capability:'.Capabilities::TICKET_ESCALATE)
                ->whereUlid('ticket')
                ->name('escalate');

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
         * What the ticket workspace puts in its selects.
         *
         * Outside the `tickets/` prefix because they are not about one ticket,
         * and gated on `ticket.read` rather than on managing the underlying
         * lists: an agent who may not edit the category list still has to pick
         * from it, and an agent who may not administer users still has to see
         * who a ticket can go to.
         */
        Route::middleware('can.capability:'.Capabilities::TICKET_READ)->group(function (): void {
            Route::get('/ticket-categories', [TicketReferenceDataController::class, 'categories'])
                ->name('ticket-categories.index');

            Route::get('/assignees', [TicketReferenceDataController::class, 'assignees'])
                ->name('assignees.index');
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
        /*
         * Staff search, outside the admin prefix.
         *
         * An agent searches from INSIDE a ticket; the URL has nothing to do
         * with administration and putting it under /admin would make the
         * busiest read in the product look like a console call.
         */
        Route::get('/knowledge/search', [ArticleSearchController::class, 'index'])
            ->middleware('can.capability:'.Capabilities::KNOWLEDGE_VIEW)
            ->name('knowledge.search');

        /*
         * The knowledge base.
         *
         * Deliberately OUTSIDE the `setting.manage` console group below,
         * though its URLs sit under the same prefix. Nesting it there would
         * mean only an administrator could write an article — and the answers
         * worth writing down are the ones an agent has just worked out on a
         * ticket. An outer gate that swallows three inner ones is a gate that
         * makes them decorative.
         *
         * Three capabilities, not one. Reading an article includes reading the
         * internal ones. Writing is a separate decision. PUBLISHING is separate
         * again, because it is the irreversible half: once published, an
         * article can never be deleted, only archived.
         */
        Route::prefix('admin/knowledge')->name('admin.knowledge.')->group(function (): void {
            Route::middleware('can.capability:'.Capabilities::KNOWLEDGE_VIEW)->group(function (): void {
                Route::get('/articles', [ArticlesController::class, 'index'])->name('articles.index');
                Route::get('/articles/{article}', [ArticlesController::class, 'show'])
                    ->whereUlid('article')->name('articles.show');
                Route::get('/categories', [ArticleCategoriesController::class, 'index'])
                    ->name('categories.index');
            });

            Route::middleware('can.capability:'.Capabilities::KNOWLEDGE_MANAGE)->group(function (): void {
                Route::post('/articles', [ArticlesController::class, 'store'])->name('articles.store');
                Route::patch('/articles/{article}', [ArticlesController::class, 'update'])
                    ->whereUlid('article')->name('articles.update');
                Route::delete('/articles/{article}', [ArticlesController::class, 'destroy'])
                    ->whereUlid('article')->name('articles.destroy');

                Route::put('/articles/{article}/translations/{locale}', [ArticleTranslationsController::class, 'upsert'])
                    ->whereUlid('article')->name('articles.translations.upsert');
                Route::delete('/articles/{article}/translations/{locale}', [ArticleTranslationsController::class, 'destroy'])
                    ->whereUlid('article')->name('articles.translations.destroy');

            });

            /*
             * Organising the library is the administrator's, not the author's.
             *
             * `setting.manage`, the same gate the ticket category list sits
             * behind. Writing an article is an agent's job; renaming a
             * category under forty of them, or deleting one, is a change
             * everybody else has to live with. Reading the list stays open to
             * anyone who may write an article — they have to file it
             * somewhere.
             */
            Route::middleware('can.capability:'.Capabilities::SETTING_MANAGE)->group(function (): void {
                Route::post('/categories', [ArticleCategoriesController::class, 'store'])
                    ->name('categories.store');
                Route::patch('/categories/{category}', [ArticleCategoriesController::class, 'update'])
                    ->name('categories.update');
                Route::delete('/categories/{category}', [ArticleCategoriesController::class, 'destroy'])
                    ->name('categories.destroy');
            });

            Route::middleware('can.capability:'.Capabilities::KNOWLEDGE_PUBLISH)->group(function (): void {
                Route::post('/articles/{article}/publish', [ArticleLifecycleController::class, 'publish'])
                    ->whereUlid('article')->name('articles.publish');
                Route::post('/articles/{article}/archive', [ArticleLifecycleController::class, 'archive'])
                    ->whereUlid('article')->name('articles.archive');
            });
        });

        Route::middleware('can.capability:'.Capabilities::SETTING_MANAGE)
            ->prefix('admin')
            ->name('admin.')
            ->group(function (): void {
                Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
                Route::patch('/settings/{key}', [SettingsController::class, 'update'])
                    ->where('key', '[A-Za-z0-9_.]+')
                    ->name('settings.update');

                /*
                 * The email channel: prove it works, and see what it did.
                 *
                 * Both behind `setting.manage` with the rest of the console —
                 * the log names every address the system has written to, which
                 * is not something an agent needs and is exactly what an
                 * attacker with an agent account would want.
                 */
                Route::post('/email/test', [EmailTestSendController::class, 'store'])
                    ->name('email.test');

                /*
                 * The mail nobody could turn into a ticket.
                 *
                 * Its own capabilities rather than `setting.manage`: reading a
                 * quarantined message means reading the RAW source of a
                 * customer's email, and replaying one can open a ticket and
                 * email them. Those are different acts and deserve different
                 * permissions.
                 */
                /*
                 * What a target actually means against the schedule being
                 * edited on the same screen. Answered by the same calculator
                 * the timers use, so a preview cannot promise something the
                 * engine will not deliver.
                 */
                Route::get('/sla/preview', [SlaPreviewController::class, 'show'])
                    ->name('sla.preview');

                Route::get('/email/quarantine', [MailQuarantineController::class, 'index'])
                    ->middleware('can.capability:'.Capabilities::QUARANTINE_VIEW)
                    ->name('email.quarantine.index');

                Route::get('/email/quarantine/{id}', [MailQuarantineController::class, 'show'])
                    ->middleware('can.capability:'.Capabilities::QUARANTINE_VIEW)
                    ->whereUlid('id')
                    ->name('email.quarantine.show');

                Route::post('/email/quarantine/{id}/replay', [MailQuarantineController::class, 'replay'])
                    ->middleware('can.capability:'.Capabilities::QUARANTINE_REPLAY)
                    ->whereUlid('id')
                    ->name('email.quarantine.replay');

                /*
                 * The channel list an administrator switches on and off.
                 *
                 * Its own capability rather than `setting.manage`: disabling a
                 * channel stops customers reaching the desk through it, which
                 * is an operational decision with a visible consequence, not a
                 * preference.
                 */
                Route::get('/channels', [ChannelAccountsController::class, 'index'])
                    ->middleware('can.capability:'.Capabilities::CHANNEL_MANAGE)
                    ->name('channels.index');

                Route::patch('/channels/{id}', [ChannelAccountsController::class, 'update'])
                    ->middleware('can.capability:'.Capabilities::CHANNEL_MANAGE)
                    ->whereUlid('id')
                    ->name('channels.update');

                /*
                 * Where new tickets land. A lookup table an administrator
                 * reads in one line per row — no conditions, no ordering, no
                 * enable switch, because `unique(source_type, source_id)`
                 * leaves nothing to resolve.
                 *
                 * Behind `setting.manage` with the rest of the console: this
                 * decides where everybody else's work arrives.
                 */
                Route::get('/assignment-mappings', [AssignmentMappingsController::class, 'index'])
                    ->name('assignment-mappings.index');
                Route::post('/assignment-mappings', [AssignmentMappingsController::class, 'store'])
                    ->name('assignment-mappings.store');
                Route::patch('/assignment-mappings/{mapping}', [AssignmentMappingsController::class, 'update'])
                    ->name('assignment-mappings.update');
                Route::delete('/assignment-mappings/{mapping}', [AssignmentMappingsController::class, 'destroy'])
                    ->name('assignment-mappings.destroy');

                Route::get('/email/log', [MailLogController::class, 'index'])
                    ->name('email.log');

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
    /*
     * Where a provider delivers a customer's email.
     *
     * THE ONE unauthenticated write in this system. It sits outside every auth
     * group on purpose: the caller is a machine with no session, and putting it
     * behind Sanctum would mean either giving a provider an account or
     * exempting the route anyway.
     *
     * Its guard is a shared secret compared in constant time, checked in the
     * controller. It is also exempt from the Idempotency-Key requirement —
     * providers do not send one, and idempotency here is enforced on the
     * message's own id, which is stronger: it survives a retry from a different
     * provider process with a different header.
     */
    Route::post('/inbound/email', [InboundWebhookController::class, 'store'])
        ->withoutMiddleware([IdempotencyKey::class])
        ->name('inbound.email');

    /*
     * The public web form: the second unauthenticated write, and the first one
     * a PERSON uses.
     *
     * It has no shared secret, because the caller is a stranger's browser and
     * there is nowhere to put one. What stands in for it is bot protection the
     * controller applies — a honeypot and a minimum fill time — and two rate
     * limiters registered in ChannelsServiceProvider: one by IP, one by the
     * contact given, because a botnet defeats the first and a script defeats
     * the second.
     *
     * Exempt from Idempotency-Key for the same reason the mail webhook is: a
     * browser form does not send one, and idempotency here is enforced on the
     * minted message id, which is stronger.
     */
    /*
     * The customer help centre. Public, like the web form beside it.
     *
     * Requiring an account to read an answer is a help centre that only helps
     * people who already got in — and the whole point of writing the answer
     * down was to stop somebody having to ask for it.
     *
     * Its own controller and its own query: every field a customer must not
     * see is one this path never fetches.
     */
    Route::prefix('help')->name('help.')->group(function (): void {
        Route::get('/articles', [HelpCentreController::class, 'index'])->name('articles.index');
        Route::get('/articles/{article}', [HelpCentreController::class, 'show'])
            ->whereUlid('article')
            ->name('articles.show');
    });

    /*
     * The chat widget's four endpoints, and nothing else it can reach.
     *
     * Unauthenticated in the sense that there is no session and no user. The
     * authorisation is an http-only cookie holding a token scoped to exactly
     * one conversation: it resolves to nobody, grants no capability, and there
     * is no endpoint in this group that offers a ticket, a customer or a list.
     * A stolen token reads and continues one conversation until it expires.
     *
     * Exempt from Idempotency-Key: the widget is a browser, browsers do not
     * send one, and a repeated `send` is a visitor pressing enter twice — which
     * is two messages, honestly recorded, not a duplicate to swallow.
     */
    /*
     * The brand, for the surfaces a customer reaches before signing in.
     *
     * Public because the portal, the public form and the widget all draw
     * themselves before anybody has authenticated — and three presentation
     * values on pages anybody can already load give nothing away.
     */
    Route::get('/branding', [BrandingController::class, 'show'])->name('branding.show');

    Route::prefix('chat')->name('chat.')->withoutMiddleware([IdempotencyKey::class])->group(function (): void {
        Route::get('/embed-origins', [ChatWidgetController::class, 'embedOrigins'])->name('origins');
        Route::post('/conversations', [ChatWidgetController::class, 'open'])->name('open');
        Route::get('/conversations/current', [ChatWidgetController::class, 'current'])->name('current');
        Route::get('/conversations/current/messages', [ChatWidgetController::class, 'messages'])->name('messages');
        Route::post('/conversations/current/messages', [ChatWidgetController::class, 'send'])->name('send');
        Route::post('/conversations/current/close', [ChatWidgetController::class, 'close'])->name('close');
    });

    /*
     * "How did it go?", answered from an inbox.
     *
     * Unauthenticated in the sense that there is no session, and authorised in
     * the only sense that matters: the `signed` middleware proves this exact
     * URL — this ticket, this answer, this expiry — was minted by us and
     * emailed to the address on the ticket. Editing the id in the address bar
     * invalidates the signature, so a guessed ticket is refused before the
     * controller runs.
     *
     * GET is the tap in the email; POST is the thank-you page sending the
     * optional comment back to the same signed URL. Exempt from
     * Idempotency-Key because an email client cannot send one, and because a
     * repeat is harmless here by construction: rating twice with the same
     * answer leaves the ticket exactly as it was.
     */
    Route::match(['get', 'post'], '/feedback/{ticket}/{verdict}', FeedbackInvitationController::class)
        ->middleware('signed')
        ->whereUlid('ticket')
        ->whereIn('verdict', ['up', 'down'])
        ->withoutMiddleware([IdempotencyKey::class])
        ->name('tickets.feedback.invitation');

    /*
     * WhatsApp and SMS: one signed route per channel, and a second for the
     * receipts those providers send back.
     *
     * A ROUTE, not the deferred webhook subsystem — FR-128 stays deferred and
     * this deliberately does not grow into a general framework. The signature
     * is verified before the payload is read, against the secrets of the
     * ACTIVE accounts on that channel: a disabled channel's secret does not
     * open the door, because stopping inbound is the whole point of the
     * switch.
     *
     * Exempt from Idempotency-Key like the mail webhook: providers do not send
     * one, and idempotency here is enforced on the provider's own message id,
     * which survives a retry from a different process.
     */
    Route::post('/inbound/{channel}', [PhoneWebhookController::class, 'store'])
        ->whereIn('channel', ['whatsapp', 'sms'])
        ->withoutMiddleware([IdempotencyKey::class])
        ->name('inbound.phone');

    Route::post('/inbound/{channel}/receipts', [PhoneWebhookController::class, 'receipt'])
        ->whereIn('channel', ['whatsapp', 'sms'])
        ->withoutMiddleware([IdempotencyKey::class])
        ->name('inbound.phone.receipts');

    Route::prefix('inbound/web-form')->name('channels.web_form.')->group(function (): void {
        Route::get('/session', [WebFormIntakeController::class, 'session'])->name('session');

        /*
         * `withoutMiddleware` per route, not on the group.
         *
         * Chained after `group()` it applies to the registrar and not to the
         * routes inside it, which fails silently: every request is refused for
         * a missing Idempotency-Key that a browser form was never going to
         * send.
         */
        Route::post('/attachments', [WebFormAttachmentController::class, 'store'])
            ->middleware('throttle:web-form-ip')
            ->withoutMiddleware([IdempotencyKey::class])
            ->name('attachments');

        Route::post('/', [WebFormIntakeController::class, 'store'])
            ->middleware(['throttle:web-form-ip', 'throttle:web-form-identifier'])
            ->withoutMiddleware([IdempotencyKey::class])
            ->name('store');
    });

    /*
     * The portal's unauthenticated doors.
     *
     * Outside `auth:portal` by necessity — somebody registering or recovering
     * an account has no session yet — and rate-limited because of it. Each
     * limiter is named and registered in PortalServiceProvider::boot.
     */
    Route::prefix('portal/auth')->name('portal.auth.')->group(function (): void {
        Route::post('/register', [PortalAuthController::class, 'register'])
            ->middleware('throttle:portal-register')
            ->name('register');

        Route::post('/login', [PortalAuthController::class, 'login'])
            ->middleware('throttle:portal-login')
            ->name('login');

        Route::post('/password/forgot', [PortalPasswordController::class, 'forgot'])
            ->middleware('throttle:portal-password')
            ->name('password.forgot');

        Route::post('/password/reset', [PortalPasswordController::class, 'reset'])
            ->middleware('throttle:portal-password')
            ->name('password.reset');
    });

    Route::middleware('auth:portal')->prefix('portal')->name('portal.')->group(function (): void {
        Route::post('/auth/logout', [PortalAuthController::class, 'logout'])->name('auth.logout');
        Route::get('/auth/me', [PortalAuthController::class, 'me'])->name('auth.me');

        /*
         * The customer's own requests.
         *
         * `requests`, not `tickets`: the URL is the one piece of internal
         * vocabulary a customer actually sees, and "ticket" is what the desk
         * calls it. Somebody who asked about their invoice did not file a
         * ticket.
         *
         * The older `/portal/tickets` pair is kept below so nothing already
         * pointing at it breaks; it answers from the same gateway.
         */
        Route::get('/requests', [PortalRequestsController::class, 'index'])->name('requests.index');
        Route::post('/requests', [PortalRequestsController::class, 'store'])->name('requests.store');

        /*
         * How it went. A POST to a named sub-resource, not a PATCH on the
         * request — a PATCH carries `If-Match`, and a customer has no version
         * to be stale against and never sees one.
         */
        Route::post('/requests/{id}/rating', [PortalRequestsController::class, 'rate'])
            ->whereUlid('id')
            ->name('requests.rate');

        Route::get('/requests/{id}', [PortalRequestsController::class, 'show'])
            ->whereUlid('id')->name('requests.show');

        Route::post('/requests/{id}/replies', [PortalRequestsController::class, 'reply'])
            ->whereUlid('id')->name('requests.reply');

        Route::post('/requests/{id}/reopen', [PortalRequestsController::class, 'reopen'])
            ->whereUlid('id')->name('requests.reopen');

        Route::get('/tickets', [PortalRequestsController::class, 'index'])->name('tickets.index');
        Route::post('/tickets', [PortalRequestsController::class, 'store'])->name('tickets.store');
    });
});
