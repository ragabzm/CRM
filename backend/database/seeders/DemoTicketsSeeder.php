<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Customers\Domain\Customer;
use App\Modules\Portal\Domain\PortalAccount;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Category;
use App\Modules\Tickets\Domain\Commands\AppendMessage;
use App\Modules\Tickets\Domain\Commands\AssignTicket;
use App\Modules\Tickets\Domain\Commands\ChangeStatus;
use App\Modules\Tickets\Domain\Commands\CreateTicket;
use App\Modules\Tickets\Domain\Commands\CreateTicketInput;
use App\Modules\Tickets\Domain\Commands\ResolveTicket;
use App\Modules\Tickets\Domain\Enum\MessageDirection;
use App\Modules\Tickets\Domain\Enum\TicketChannel;
use App\Modules\Tickets\Domain\Enum\TicketStatus;
use App\Modules\Tickets\Domain\Events\AgentReplyPosted;
use App\Modules\Tickets\Domain\Priority;
use App\Modules\Tickets\Domain\Ticket;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Event;

/**
 * Eighteen tickets, every one of them built by the commands.
 *
 * This is the seeder the architecture rule exists for. A ticket is not a row:
 * it is a row, an append-only `ticket_events` entry per thing that happened to
 * it, a version, and timestamps a lifecycle decides. `DB::table('tickets')
 * ->insert()` produces something that looks right in a list and is wrong
 * everywhere it matters — no `ticket.created` event, so the history starts
 * mid-story; a version of nothing, so the first edit from the interface is
 * refused as stale.
 *
 * So every write here goes through `CreateTicket`, `AppendMessage`,
 * `AssignTicket`, `ChangeStatus` and `ResolveTicket`, with an explicit
 * `Actor` — the same path the HTTP layer takes. It is slower than an insert
 * and it is the only version that produces a database the application can
 * actually work with.
 *
 * Order within a blueprint is deliberate: create, then messages, then
 * assignment, then the status move LAST. An inbound message on a `pending`
 * ticket flips it back to `open` (that is `ReopenOnCustomerReply` doing its
 * job), so writing the status first would leave every "pending" ticket open.
 *
 * The helpers below stay private. They are the first reusable builders in the
 * codebase, and promoting them to a shared `Database\Builders\` namespace is
 * deferred until something other than this file needs them — a shared
 * abstraction with one caller is just a longer way to write the caller.
 */
final class DemoTicketsSeeder extends Seeder
{
    private const AGENT_GENERAL = 'agent@ragab.test';

    private const AGENT_SUPPORT = 'agent.support@ragab.test';

    private const AGENT_SALES = 'agent.sales@ragab.test';

    private const AGENT_BILLING = 'agent.billing@ragab.test';

    public function run(): void
    {
        if (! DemoEnvironment::allows($this->command, self::class)) {
            return;
        }

        DemoEnvironment::needs($this, DemoAgentsSeeder::class, static fn (): bool => User::query()->whereNotNull('department_id')->exists());
        DemoEnvironment::needs($this, DemoCategoriesSeeder::class, static fn (): bool => Category::query()->exists());
        DemoEnvironment::needs($this, DemoCustomersSeeder::class, static fn (): bool => Customer::query()->exists());
        DemoEnvironment::needs($this, DemoPortalAccountsSeeder::class, static fn (): bool => PortalAccount::query()->exists());

        $create = app(CreateTicket::class);
        $append = app(AppendMessage::class);
        $assign = app(AssignTicket::class);
        $status = app(ChangeStatus::class);
        $resolve = app(ResolveTicket::class);

        $agents = User::query()
            ->whereIn('email', [self::AGENT_GENERAL, self::AGENT_SUPPORT, self::AGENT_SALES, self::AGENT_BILLING])
            ->get()
            ->keyBy('email');

        $categories = Category::query()->pluck('id', 'name_en');

        $tickets = 0;
        $messages = 0;
        $notes = 0;

        /*
         * Seeded replies do not try to email anybody.
         *
         * `email.enabled` defaults to false — deliberately, so a fresh install
         * cannot mail real customers on boot — which means every outbound
         * message the seeder wrote came back `failed: the email channel is
         * switched off`, and left a row in `failed_jobs`. The result was a
         * demo dataset where every single agent reply carried a red "Not
         * sent" chip, on the most-looked-at screen in the product.
         *
         * Neither state was true. These messages are a story about the past;
         * nobody pressed Send, so nothing failed to arrive. Suppressing the
         * event is the honest version — the same reasoning as
         * `suppressAcknowledgement` above.
         *
         * `fakeFor` and not `fake`: the dispatcher is restored afterwards, so
         * a seeder run inside a longer process does not silently disarm every
         * listener that comes after it.
         */
        Event::fakeFor(function () use (&$tickets, &$messages, &$notes, $create, $append, $assign, $status, $resolve, $agents, $categories): void {
            $this->buildAll($tickets, $messages, $notes, $create, $append, $assign, $status, $resolve, $agents, $categories);
        }, [AgentReplyPosted::class]);

        $this->command?->info("Seeded {$tickets} tickets, {$messages} messages, {$notes} internal notes.");
    }

    /**
     * @param  \Illuminate\Support\Collection<string, User>  $agents
     * @param  \Illuminate\Support\Collection<string, int>  $categories
     */
    private function buildAll(
        int &$tickets,
        int &$messages,
        int &$notes,
        CreateTicket $create,
        AppendMessage $append,
        AssignTicket $assign,
        ChangeStatus $status,
        ResolveTicket $resolve,
        $agents,
        $categories,
    ): void {
        foreach (self::blueprints() as $blueprint) {
            $customer = DemoCustomersSeeder::findByEmail($blueprint['customer']);

            if ($customer === null) {
                continue;
            }

            if (self::existing($blueprint['subject'], (string) $customer->getKey()) !== null) {
                // Already seeded. Skipping the WHOLE blueprint, not just the
                // ticket: re-running the message and status steps against an
                // existing ticket would append a second copy of every message
                // and record a status change to the status it is already in.
                continue;
            }

            $ticket = $create->handle(
                self::openedBy($blueprint, $customer),
                new CreateTicketInput(
                    subject: $blueprint['subject'],
                    description: $blueprint['description'],
                    customerId: (string) $customer->getKey(),
                    channel: $blueprint['channel'],
                    categoryId: $categories[$blueprint['category']] ?? null,
                    priority: $blueprint['priority'],
                    departmentId: $customer->department_id === null ? null : (int) $customer->department_id,
                    /*
                     * No acknowledgement email. Seeding is not a customer
                     * getting in touch, and a full seed would otherwise hand
                     * the mail transport 18 messages nobody asked for.
                     */
                    suppressAcknowledgement: true,
                ),
            );

            $tickets++;

            foreach ($blueprint['messages'] as [$direction, $body]) {
                $append->handle(
                    self::wrote($direction, $blueprint, $customer, $agents),
                    (string) $ticket->getKey(),
                    $direction,
                    $body,
                );

                $messages++;

                if ($direction === MessageDirection::Internal) {
                    $notes++;
                }
            }

            $assignee = $blueprint['assignee'] === null ? null : $agents->get($blueprint['assignee']);

            if ($assignee !== null) {
                $ticket = $assign->handle(
                    self::staff($assignee),
                    (string) $ticket->getKey(),
                    // The version as it stands after the messages. Staff are
                    // NOT exempt from the version guard, and passing null
                    // would be refused as stale — correctly.
                    $ticket->fresh()?->version,
                    (int) $assignee->getKey(),
                );
            }

            $this->settle($blueprint, $ticket, $assignee, $agents, $status, $resolve);
        }
    }

    /**
     * Walks the ticket to the status its blueprint asks for.
     *
     * Through the real transitions, one step at a time. `closed` is not a move
     * a ticket can make from `open` — it goes open → resolved → closed — and
     * forcing the column would produce a closed ticket that was never resolved
     * in its own history.
     *
     * @param  array<string, mixed>  $blueprint
     * @param  \Illuminate\Support\Collection<string, User>  $agents
     */
    private function settle(
        array $blueprint,
        Ticket $ticket,
        ?User $assignee,
        $agents,
        ChangeStatus $status,
        ResolveTicket $resolve,
    ): void {
        $target = $blueprint['status'];

        if ($target === TicketStatus::Open) {
            return;
        }

        // Whoever is holding it, or the supervisor-shaped fallback for the
        // unassigned ones. Somebody has to have done it.
        $actor = self::staff($assignee ?? $agents->get(self::AGENT_GENERAL));
        $id = (string) $ticket->getKey();

        if ($target === TicketStatus::Pending) {
            $status->handle($actor, $id, $ticket->fresh()?->version, TicketStatus::Pending);

            return;
        }

        $ticket = $resolve->handle($actor, $id, $ticket->fresh()?->version, $blueprint['resolution']);

        if ($target === TicketStatus::Closed) {
            $status->handle($actor, $id, $ticket->fresh()?->version, TicketStatus::Closed);
        }
    }

    /**
     * Who opened it — decided by the channel, not by convenience.
     *
     * A portal ticket was opened by the person signed in to the portal; an
     * email arrived through the intake, which is the system; an agent ticket
     * was typed by an agent taking a call. Recording all three as the same
     * actor would make `creator_type` a column that never tells you anything.
     *
     * @param  array<string, mixed>  $blueprint
     */
    private static function openedBy(array $blueprint, Customer $customer): Actor
    {
        if ($blueprint['channel'] === TicketChannel::Portal) {
            $account = PortalAccount::query()->where('customer_id', $customer->getKey())->first();

            if ($account !== null) {
                return Actor::portal((string) $account->getKey(), (string) $account->name);
            }
        }

        return match ($blueprint['channel']) {
            TicketChannel::Email => Actor::system('inbound email'),
            TicketChannel::System => Actor::system('demo data'),
            default => Actor::staff('1', 'Front desk'),
        };
    }

    /**
     * @param  array<string, mixed>  $blueprint
     * @param  \Illuminate\Support\Collection<string, User>  $agents
     */
    private static function wrote(
        MessageDirection $direction,
        array $blueprint,
        Customer $customer,
        $agents,
    ): Actor {
        if ($direction === MessageDirection::Inbound) {
            return self::openedBy($blueprint, $customer);
        }

        // Outbound and internal are both written by a person on the desk. The
        // assignee where there is one, so the thread reads as a conversation
        // with somebody rather than with the building.
        $author = ($blueprint['assignee'] === null ? null : $agents->get($blueprint['assignee']))
            ?? $agents->get(self::AGENT_GENERAL);

        return self::staff($author);
    }

    private static function staff(?User $user): Actor
    {
        return $user === null
            ? Actor::system('demo data')
            : Actor::staff((string) $user->getKey(), (string) $user->name);
    }

    /**
     * The idempotency key: this subject, for this customer.
     *
     * Not the reference — references come from the allocator and are different
     * on every fresh database, so keying on one would seed a second copy of
     * everything on the second run.
     */
    public static function existing(string $subject, string $customerId): ?Ticket
    {
        return Ticket::query()
            ->where('subject', $subject)
            ->where('customer_id', $customerId)
            ->first();
    }

    /**
     * The fixed catalogue.
     *
     * Hard-coded rather than generated, because the DISTRIBUTION is the point:
     * every status, every priority, every channel, four agents each holding
     * more than one ticket, two tickets nobody has picked up, and three
     * threads carrying an internal note. A random generator would produce a
     * plausible list that quietly stopped covering one of those the first time
     * the seed changed.
     *
     * @return list<array{
     *   subject: string, description: string, customer: string,
     *   channel: TicketChannel, priority: Priority, status: TicketStatus,
     *   category: string, assignee: string|null, resolution: string,
     *   messages: list<array{MessageDirection, string}>
     * }>
     */
    public static function blueprints(): array
    {
        $reply = static fn (string $inbound, string $outbound): array => [
            [MessageDirection::Inbound, $inbound],
            [MessageDirection::Outbound, $outbound],
        ];

        $withNote = static fn (string $inbound, string $outbound, string $note): array => [
            [MessageDirection::Inbound, $inbound],
            [MessageDirection::Internal, $note],
            [MessageDirection::Outbound, $outbound],
        ];

        return [
            [
                'subject' => 'الفاتورة فيها رسم مكرر',
                'description' => 'اتخصم مني نفس المبلغ مرتين في نفس اليوم.',
                'customer' => 'layla.haddad@example.test',
                'channel' => TicketChannel::Email,
                'priority' => Priority::High,
                'status' => TicketStatus::Open,
                'category' => 'Billing',
                'assignee' => self::AGENT_SUPPORT,
                'resolution' => '',
                'messages' => $withNote(
                    'اتخصم مني نفس المبلغ مرتين في نفس اليوم.',
                    'شكراً للتنبيه — بنراجع كشف الحساب ونرد عليكِ النهاردة.',
                    'Third duplicate charge reported this week. Worth checking whether the gateway is retrying.',
                ),
            ],
            [
                'subject' => 'Waiting on a copy of my contract',
                'description' => 'I asked for a signed copy last week and have not received it.',
                'customer' => 'layla.haddad@example.test',
                'channel' => TicketChannel::Portal,
                'priority' => Priority::Normal,
                'status' => TicketStatus::Pending,
                'category' => 'Account',
                'assignee' => self::AGENT_SUPPORT,
                'resolution' => '',
                'messages' => $reply(
                    'I asked for a signed copy last week and have not received it.',
                    'We have asked the accounts team to send it. Waiting on them now.',
                ),
            ],
            [
                'subject' => 'Password reset link never arrives',
                'description' => 'Requested three times, nothing in the inbox or spam.',
                'customer' => 'yusuf.salim@example.test',
                'channel' => TicketChannel::Agent,
                'priority' => Priority::Low,
                'status' => TicketStatus::Resolved,
                'category' => 'Technical',
                'assignee' => self::AGENT_SUPPORT,
                'resolution' => 'Address had a typo on the account; corrected and the link arrived.',
                'messages' => $reply(
                    'Requested the reset three times and nothing arrives.',
                    'Your address was recorded with a typo. Corrected — try once more.',
                ),
            ],
            [
                'subject' => 'Cannot sign in since this morning',
                'description' => 'The page just reloads without an error.',
                'customer' => 'yusuf.salim@example.test',
                'channel' => TicketChannel::Email,
                'priority' => Priority::Urgent,
                'status' => TicketStatus::Open,
                'category' => 'Technical',
                'assignee' => self::AGENT_GENERAL,
                'resolution' => '',
                'messages' => $reply(
                    'The sign-in page just reloads and never lets me in.',
                    'Thanks — we can reproduce it. Someone is looking at it now.',
                ),
            ],
            [
                'subject' => 'Quote for twenty more seats',
                'description' => 'We are growing and need pricing for twenty additional users.',
                'customer' => 'omar.farouk@example.test',
                'channel' => TicketChannel::Portal,
                'priority' => Priority::Normal,
                'status' => TicketStatus::Open,
                'category' => 'General',
                'assignee' => self::AGENT_SALES,
                'resolution' => '',
                'messages' => $reply(
                    'We need pricing for twenty additional users.',
                    'Happy to help. Sending a quote today with the volume tier applied.',
                ),
            ],
            [
                'subject' => 'طلب عرض سعر للتجديد السنوي',
                'description' => 'الاشتراك بيخلص الشهر الجاي وعايزين نجدد.',
                'customer' => 'omar.farouk@example.test',
                'channel' => TicketChannel::Agent,
                'priority' => Priority::High,
                'status' => TicketStatus::Closed,
                'category' => 'General',
                'assignee' => self::AGENT_SALES,
                'resolution' => 'Renewal quote accepted and the order was raised.',
                'messages' => $withNote(
                    'الاشتراك بيخلص الشهر الجاي وعايزين نجدد بنفس الشروط.',
                    'اتبعتلك عرض التجديد. سعره زي السنة اللي فاتت.',
                    'Renewed every year since 2023. Safe to quote the same tier without approval.',
                ),
            ],
            [
                'subject' => 'Invoice address is out of date',
                'description' => 'We moved offices and the invoices still show the old address.',
                'customer' => 'karim.zahran@example.test',
                'channel' => TicketChannel::Email,
                'priority' => Priority::Low,
                'status' => TicketStatus::Pending,
                'category' => 'Billing',
                'assignee' => self::AGENT_SALES,
                'resolution' => '',
                'messages' => $reply(
                    'We moved offices and the invoices still show the old address.',
                    'Updated on our side. Waiting for finance to reissue this month.',
                ),
            ],
            [
                'subject' => 'Scheduled maintenance notice',
                'description' => 'Automated notice raised for the planned window on Saturday.',
                'customer' => 'karim.zahran@example.test',
                'channel' => TicketChannel::System,
                'priority' => Priority::Normal,
                'status' => TicketStatus::Open,
                'category' => 'General',
                // Nobody has picked this up. A queue where every ticket
                // already belongs to somebody never shows the pool.
                'assignee' => null,
                'resolution' => '',
                'messages' => $reply(
                    'Automated notice: planned maintenance on Saturday 02:00-04:00.',
                    'Noted and acknowledged.',
                ),
            ],
            [
                'subject' => 'Refund has not landed after ten days',
                'description' => 'The refund was confirmed on the 3rd and still has not arrived.',
                'customer' => 'sarah.nasser@example.test',
                'channel' => TicketChannel::Email,
                'priority' => Priority::Urgent,
                'status' => TicketStatus::Resolved,
                'category' => 'Billing',
                'assignee' => self::AGENT_BILLING,
                'resolution' => 'Bank returned it once for a stale account number; reissued and cleared.',
                'messages' => $reply(
                    'The refund was confirmed on the 3rd and still has not arrived.',
                    'It bounced back once. Reissued today and it should clear within two days.',
                ),
            ],
            [
                'subject' => 'Please split the invoice across two cost centres',
                'description' => 'Finance needs the monthly invoice split 60/40.',
                'customer' => 'sarah.nasser@example.test',
                'channel' => TicketChannel::Email,
                'priority' => Priority::Normal,
                'status' => TicketStatus::Open,
                'category' => 'Billing',
                'assignee' => self::AGENT_BILLING,
                'resolution' => '',
                'messages' => $withNote(
                    'Finance needs the monthly invoice split 60/40 across two cost centres.',
                    'We can do that from next month. Confirming the split with billing.',
                    'Billing system cannot split automatically — this is a manual job every month.',
                ),
            ],
            [
                'subject' => 'Close the account at the end of the term',
                'description' => 'We are not renewing and want the account closed cleanly.',
                'customer' => 'nadia.kassem@example.test',
                'channel' => TicketChannel::Agent,
                'priority' => Priority::High,
                'status' => TicketStatus::Closed,
                'category' => 'Account',
                'assignee' => self::AGENT_BILLING,
                'resolution' => 'Account closed at term end and the final invoice was settled.',
                'messages' => $reply(
                    'We are not renewing. Please close the account at the end of the term.',
                    'Understood. The final invoice goes out on the last day of the term.',
                ),
            ],
            [
                'subject' => 'Final invoice shows a pro-rata line I do not recognise',
                'description' => 'There is a pro-rata charge on the closing invoice.',
                'customer' => 'nadia.kassem@example.test',
                'channel' => TicketChannel::Email,
                'priority' => Priority::Normal,
                'status' => TicketStatus::Open,
                'category' => 'Billing',
                'assignee' => null,
                'resolution' => '',
                'messages' => $reply(
                    'There is a pro-rata charge on the closing invoice I do not recognise.',
                    'Thanks — we are checking what that line covers.',
                ),
            ],
            [
                'subject' => 'إضافة رقم تليفون تاني للحساب',
                'description' => 'عايزة أضيف رقم تاني للتواصل.',
                'customer' => 'layla.haddad@example.test',
                'channel' => TicketChannel::Portal,
                'priority' => Priority::Normal,
                'status' => TicketStatus::Open,
                'category' => 'Account',
                'assignee' => self::AGENT_GENERAL,
                'resolution' => '',
                'messages' => $reply(
                    'عايزة أضيف رقم تليفون تاني للتواصل على الحساب.',
                    'تمام — ابعتيلنا الرقم وهنضيفه.',
                ),
            ],
            [
                'subject' => 'Export stops at ten thousand rows',
                'description' => 'The CSV export is truncated at exactly 10,000 rows.',
                'customer' => 'yusuf.salim@example.test',
                'channel' => TicketChannel::Agent,
                'priority' => Priority::High,
                'status' => TicketStatus::Pending,
                'category' => 'Technical',
                'assignee' => self::AGENT_SUPPORT,
                'resolution' => '',
                'messages' => $reply(
                    'Every CSV export stops at exactly ten thousand rows.',
                    'That is a known cap. Raised it with engineering and waiting on a date.',
                ),
            ],
            [
                'subject' => 'Purchase order number missing from the invoice',
                'description' => 'Our PO number has to appear on every invoice.',
                'customer' => 'omar.farouk@example.test',
                'channel' => TicketChannel::Email,
                'priority' => Priority::Normal,
                'status' => TicketStatus::Resolved,
                'category' => 'Billing',
                'assignee' => self::AGENT_SALES,
                'resolution' => 'PO number added to the account so it prints on every future invoice.',
                'messages' => $reply(
                    'Our PO number has to appear on every invoice and it is missing.',
                    'Added to the account — it will print on everything from now on.',
                ),
            ],
            [
                'subject' => 'Monthly usage summary',
                'description' => 'Automated summary of last month usage.',
                'customer' => 'sarah.nasser@example.test',
                'channel' => TicketChannel::System,
                'priority' => Priority::Low,
                'status' => TicketStatus::Open,
                'category' => 'General',
                'assignee' => self::AGENT_GENERAL,
                'resolution' => '',
                'messages' => $reply(
                    'Automated summary: usage was within the plan allowance last month.',
                    'Filed for the account review.',
                ),
            ],
            [
                'subject' => 'اقتراح لتحسين صفحة الطلبات',
                'description' => 'الصفحة محتاجة فلتر بالتاريخ.',
                'customer' => 'layla.haddad@example.test',
                'channel' => TicketChannel::System,
                'priority' => Priority::Normal,
                'status' => TicketStatus::Closed,
                'category' => 'Feedback',
                'assignee' => self::AGENT_SUPPORT,
                'resolution' => 'Passed to the product team and the ticket was closed.',
                'messages' => $reply(
                    'صفحة الطلبات محتاجة فلتر بالتاريخ.',
                    'اقتراح كويس — وصّلناه لفريق المنتج.',
                ),
            ],
            [
                'subject' => 'Card was declined on the renewal charge',
                'description' => 'The renewal payment failed and we need to pay another way.',
                'customer' => 'karim.zahran@example.test',
                'channel' => TicketChannel::Email,
                'priority' => Priority::Urgent,
                'status' => TicketStatus::Open,
                'category' => 'Billing',
                'assignee' => self::AGENT_GENERAL,
                'resolution' => '',
                'messages' => $reply(
                    'The renewal payment was declined and we need to pay another way.',
                    'No problem — sending a bank transfer link now.',
                ),
            ],
        ];
    }
}
