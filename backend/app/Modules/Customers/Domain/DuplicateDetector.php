<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

use Illuminate\Support\Facades\DB;

/**
 * Finds customers who already hold one of the identifiers being entered.
 *
 * It OFFERS. It never blocks. The database is happy to hold two customers with
 * the same phone number, because two people in a household genuinely share one,
 * and a system that refuses the second is a system an agent has to work around
 * while a real person waits on the line.
 *
 * What it prevents is the accidental case: the same customer entered twice
 * because nobody searched first. The answer to that is showing the match at the
 * moment of entry, not a constraint that also catches the legitimate case.
 *
 * Inactive customers are included in the results. Someone returning after two
 * years is exactly the duplicate worth catching, and their record already holds
 * the history the new one would lack.
 */
final class DuplicateDetector
{
    /**
     * @param  list<string>  $emails
     * @param  list<string>  $phones
     * @return list<array{customer_id:string,reference:string,full_name:string,state:string,matched_value:string,matched_kind:string}>
     */
    public function preview(array $emails, array $phones, ?string $excludeCustomerId = null): array
    {
        $normalisedEmails = $this->normaliseAll(ContactKind::Email, $emails);
        $normalisedPhones = $this->normaliseAll(ContactKind::Phone, $phones);

        if ($normalisedEmails === [] && $normalisedPhones === []) {
            return [];
        }

        // One query, not one per identifier: a form with four identifiers must
        // not become four round trips on every keystroke-triggered preview.
        $query = DB::table('contact_identifiers as ci')
            ->join('customers as c', 'c.id', '=', 'ci.customer_id')
            ->select([
                'c.id as customer_id',
                'c.reference',
                'c.full_name',
                'c.state',
                'ci.value as matched_value',
                'ci.kind as matched_kind',
            ])
            ->where(function ($where) use ($normalisedEmails, $normalisedPhones): void {
                if ($normalisedEmails !== []) {
                    $where->orWhere(function ($clause) use ($normalisedEmails): void {
                        $clause->where('ci.kind', ContactKind::Email->value)
                            ->whereIn('ci.value_normalised', $normalisedEmails);
                    });
                }

                if ($normalisedPhones !== []) {
                    $where->orWhere(function ($clause) use ($normalisedPhones): void {
                        $clause->where('ci.kind', ContactKind::Phone->value)
                            ->whereIn('ci.value_normalised', $normalisedPhones);
                    });
                }
            });

        if ($excludeCustomerId !== null) {
            // Editing a customer must not offer that customer as their own
            // duplicate.
            $query->where('c.id', '!=', $excludeCustomerId);
        }

        /** @var list<array{customer_id:string,reference:string,full_name:string,state:string,matched_value:string,matched_kind:string}> $matches */
        $matches = $query->orderBy('c.full_name')->get()->map(
            static fn (object $row): array => (array) $row,
        )->all();

        return $matches;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function normaliseAll(ContactKind $kind, array $values): array
    {
        $normalised = [];

        foreach ($values as $value) {
            $key = IdentifierNormaliser::normalise($kind, $value);

            if ($key !== '') {
                $normalised[$key] = true;
            }
        }

        return array_keys($normalised);
    }
}
