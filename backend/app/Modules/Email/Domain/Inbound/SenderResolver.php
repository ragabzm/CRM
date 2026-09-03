<?php

declare(strict_types=1);

namespace App\Modules\Email\Domain\Inbound;

use App\Modules\Customers\Contracts\CustomerDirectory;

/**
 * Who sent this, as a customer record.
 *
 * When nobody matches, a record is CREATED rather than the mail being refused.
 * A support desk that only accepts email from people already in the database is
 * a support desk that cannot be emailed.
 *
 * Both halves go through `CustomerDirectory`. This module used to insert into
 * the `customers` and `contact_identifiers` tables itself, which meant it knew
 * the reference format and the address-normalisation rule — two things that
 * belong to Customers and that would have broken this silently the day either
 * changed.
 */
final class SenderResolver
{
    public const CREATED_VIA = 'inbound_email';

    public function __construct(private readonly CustomerDirectory $customers) {}

    /**
     * @return array{id: string, created: bool}
     */
    public function resolve(ParsedMail $mail): array
    {
        $existing = $this->customers->findIdByEmail($mail->fromAddress);

        if ($existing !== null) {
            return ['id' => $existing, 'created' => false];
        }

        return [
            'id' => $this->customers->createFromAddress(
                $mail->fromAddress,
                $mail->fromName,
                self::CREATED_VIA,
            ),
            'created' => true,
        ];
    }
}
