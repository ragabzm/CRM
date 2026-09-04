<?php

declare(strict_types=1);

namespace Tests\Feature\Platform\Audit;

use App\Models\User;
use App\Modules\Security\Domain\Roles;
use App\Modules\Tickets\Domain\Actor\Actor;
use App\Modules\Tickets\Domain\Commands\UpdateTicketAttributes;
use App\Modules\Tickets\Domain\Commands\TicketAttributeChanges;
use App\Modules\Tickets\Domain\Priority;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MakesTickets;
use Tests\TestCase;

/**
 * The audit log says who, by name.
 *
 * `AuditWriter` has a fallback that renders an actor as `user 3` when it has
 * no label, and its own comment calls that unreadable a year later. It fired
 * routinely, because the `AuditLogger` seam carried only a numeric id — so a
 * command that knew the person's name had no way to pass it, and anything
 * running outside an HTTP request had no context to recover it from either.
 *
 * The log is read after an incident, months later, by somebody who does not
 * know which id belonged to whom.
 */
final class AuditNamesTheActorTest extends TestCase
{
    use MakesTickets;
    use RefreshDatabase;

    public function test_a_ticket_change_records_the_name_of_whoever_made_it(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $agent = User::factory()->create(['name' => 'Hana Support']);
        $agent->assignRole(Roles::AGENT);

        $ticket = $this->makeTicket(['status' => 'open']);

        /*
         * Invoked directly, with no request in flight — the same shape as the
         * sweep, the reply listener and the seeders. That is precisely the
         * case the request context cannot rescue.
         */
        app(UpdateTicketAttributes::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            (string) $ticket->getKey(),
            $ticket->version,
            TicketAttributeChanges::of(['priority' => Priority::High]),
        );

        $label = DB::table('audit_entries')
            ->where('target_id', (string) $ticket->getKey())
            ->value('actor_label');

        $this->assertSame('Hana Support', $label);
    }

    public function test_it_does_not_fall_back_to_the_bare_id(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $agent = User::factory()->create(['name' => 'Hana Support']);
        $agent->assignRole(Roles::AGENT);

        $ticket = $this->makeTicket(['status' => 'open']);

        app(UpdateTicketAttributes::class)->handle(
            Actor::staff((string) $agent->getKey(), $agent->name),
            (string) $ticket->getKey(),
            $ticket->version,
            TicketAttributeChanges::of(['priority' => Priority::Urgent]),
        );

        $labels = DB::table('audit_entries')->pluck('actor_label')->all();

        foreach ($labels as $label) {
            // The shape the fallback produces. Seeing it means the name was
            // thrown away somewhere between the caller and the row.
            $this->assertDoesNotMatchRegularExpression('/^user \d+$/', (string) $label);
        }
    }

    public function test_the_system_still_says_it_was_the_system(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $ticket = $this->makeTicket(['status' => 'open']);

        app(UpdateTicketAttributes::class)->handle(
            Actor::system('auto-close sweep'),
            (string) $ticket->getKey(),
            null,
            TicketAttributeChanges::of(['priority' => Priority::Low]),
        );

        $label = (string) DB::table('audit_entries')
            ->where('target_id', (string) $ticket->getKey())
            ->value('actor_label');

        /*
         * A machine has no name to record, but it has a reason — and "the
         * sweep did it" is a far better answer than a blank or a number.
         */
        $this->assertNotSame('', $label);
        $this->assertDoesNotMatchRegularExpression('/^user \d+$/', $label);
    }
}
