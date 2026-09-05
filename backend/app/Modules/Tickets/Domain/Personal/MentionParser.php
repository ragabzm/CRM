<?php

declare(strict_types=1);

namespace App\Modules\Tickets\Domain\Personal;

use App\Models\User;
use App\Modules\Platform\Exceptions\ProblemException;

/**
 * Who did this note name?
 *
 * Answered from the STORED BODY, on the server, at write time — never from a
 * list the browser sent beside it. A client-supplied list of mentioned users
 * is a field anybody can edit, and editing it sends a colleague a notification
 * about a note that never named them.
 *
 * The syntax is what somebody would type anyway: `@` followed by a colleague's
 * name. No brackets, no ids, no markup — which means the note reads the same
 * in the composer, in the thread, in an export and in a database client, and
 * nothing has to un-parse it to show it.
 *
 * LONGEST NAME FIRST. "@Nadia Salem" and "@Nadia" can both be real people; if
 * the short one were tried first, every mention of Nadia Salem would notify
 * Nadia instead and the mistake would look like a typo in the note.
 */
final class MentionParser
{
    /**
     * Users named in this body, as ids.
     *
     * @return list<int>
     *
     * @throws ProblemException when the note names somebody who has left.
     */
    public function resolve(string $body): array
    {
        if (! str_contains($body, '@')) {
            // The overwhelmingly common case, answered without a query.
            return [];
        }

        $found = [];

        /*
         * A working copy, with each match blanked out as it is found.
         *
         * Longest-first ordering alone is NOT enough: "@Nadia" is a legitimate
         * whole-word match inside "@Nadia Salem", because the character after
         * it is a space. Without consuming the span, every mention of Nadia
         * Salem would also notify Nadia — silently, and only on desks that
         * happen to employ both.
         */
        $remaining = $body;

        foreach ($this->candidates() as $candidate) {
            if (! $this->bodyNames($remaining, (string) $candidate->name)) {
                continue;
            }

            $remaining = $this->consume($remaining, (string) $candidate->name);

            if (! (bool) $candidate->is_active) {
                /*
                 * Refused, and refused out loud.
                 *
                 * Silently dropping it would leave the writer believing they
                 * had pulled somebody in — and the note would sit there naming
                 * a person who is never going to read it. The picker does not
                 * offer deactivated colleagues; this is the same rule holding
                 * on the API, which is reachable directly.
                 */
                throw ProblemException::make(
                    'tickets.mention_inactive',
                    'That colleague is no longer active',
                    422,
                    sprintf('%s cannot be mentioned — their account has been deactivated.', $candidate->name),
                    ['name' => (string) $candidate->name],
                );
            }

            $found[(int) $candidate->id] = true;
        }

        return array_map(intval(...), array_keys($found));
    }

    /**
     * Everybody who could be named, longest name first.
     *
     * Deactivated accounts are INCLUDED here on purpose: they are the ones the
     * refusal above needs to recognise. Excluding them would turn "Omar has
     * left" into silence.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function candidates()
    {
        return User::query()
            /*
             * Staff, not customers. The `customer` role lives in the same
             * table for portal-era reasons, and a customer named in an
             * internal note would be sent the colleague's private remark
             * about them.
             */
            ->whereHas('roles', static fn ($query) => $query->where('name', '!=', 'customer'))
            ->orderByRaw('length(name) desc')
            ->get(['id', 'name', 'is_active']);
    }

    /** Replaces every `@Name` span with spaces so a shorter name cannot reuse it. */
    private function consume(string $body, string $name): string
    {
        $replaced = preg_replace_callback(
            $this->patternFor($name),
            static fn (array $m): string => str_repeat(' ', mb_strlen($m[0])),
            $body,
        );

        return $replaced ?? $body;
    }

    private function patternFor(string $name): string
    {
        return '/(?<![\p{L}\p{N}_])@'.preg_quote($name, '/').'(?![\p{L}\p{N}_])/iu';
    }

    /**
     * Does the body contain `@Name` as a whole name rather than a prefix?
     *
     * The boundary check is what stops "@Ali" matching inside "@Alia Hassan":
     * without it, mentioning Alia would also notify Ali, every time.
     */
    private function bodyNames(string $body, string $name): bool
    {
        if (trim($name) === '') {
            return false;
        }

        return preg_match($this->patternFor($name), $body) === 1;
    }
}
