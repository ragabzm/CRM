<?php

declare(strict_types=1);

namespace App\Modules\Channels\Domain\Intake;

use App\Modules\Platform\Support\Settings\SettingsRegistry;
use Illuminate\Support\Facades\DB;

/**
 * Which department a new ticket belongs to, decided once, in one place.
 *
 * Four sources, first hit wins:
 *
 *   1. `agent`   — an acting agent said so explicitly. A person's decision
 *                  outranks every rule below it.
 *   2. `channel` — the department bound to the receiving channel account.
 *                  A support mailbox and a billing mailbox are two accounts.
 *   3. `customer`— the customer's own department, for a known account with a
 *                  named team.
 *   4. `default` — the system setting, which must be set before go-live.
 *
 * The rule that matched is returned alongside the answer and recorded on the
 * creating ticket event, because "why is this in Billing?" is a question with a
 * real answer that is otherwise unrecoverable.
 *
 * When the setting is unset the answer is `unresolved`, not an exception.
 *
 * That is a deliberate choice between two bad options. Refusing the message
 * would mean a customer who wrote in gets a 500 and hears nothing, because
 * somebody did not finish configuring the system — the customer is punished for
 * an administrator's omission, and no AC anywhere is worth that. Returning null
 * silently would leave a ticket in nobody's queue with no trace of why.
 *
 * So: the ticket is created, the rule is recorded as `unresolved` on the
 * inbound message, and `artisan channels:doctor` fails — which is what gates a
 * deployment. "Cannot be null" is enforced where it can be enforced without
 * dropping a customer's words: before go-live, not in the middle of an intake.
 */
final class DepartmentResolver
{
    public const SETTING = 'channels.default_department_id';

    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * @return array{department_id: ?int, rule: string}
     */
    public function resolve(
        ?int $agentSuppliedDepartmentId,
        ?string $channelAccountId,
        ?string $customerId,
    ): array {
        if ($agentSuppliedDepartmentId !== null) {
            return ['department_id' => $agentSuppliedDepartmentId, 'rule' => 'agent'];
        }

        $fromChannel = $channelAccountId === null
            ? null
            : DB::table('channel_accounts')->where('id', $channelAccountId)->value('department_id');

        if ($fromChannel !== null) {
            return ['department_id' => (int) $fromChannel, 'rule' => 'channel'];
        }

        $fromCustomer = $customerId === null
            ? null
            : DB::table('customers')->where('id', $customerId)->value('department_id');

        if ($fromCustomer !== null) {
            return ['department_id' => (int) $fromCustomer, 'rule' => 'customer'];
        }

        $default = $this->settings->get(self::SETTING);

        return match (true) {
            is_int($default) && $default > 0 => ['department_id' => $default, 'rule' => 'default'],
            is_string($default) && ctype_digit(trim($default)) => ['department_id' => (int) trim($default), 'rule' => 'default'],
            default => ['department_id' => null, 'rule' => 'unresolved'],
        };
    }
}
