<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain;

use App\Modules\Customers\Contracts\CustomerDirectory;
use App\Modules\Platform\Exceptions\ProblemException;

/**
 * Turns one ERP record into one customer, through the Customers CONTRACT.
 *
 * Never a direct write. The Directory owns the reference format, the
 * identifier normalisation and the duplicate rule, and an integration that
 * inserted rows itself would hold copies of all three — copies that break
 * silently the day any of them changes, in a nightly job nobody watches.
 *
 * A DUPLICATE RESOLVES rather than creating a second record. Somebody whose
 * email is already on file and who appears in tonight's ERP export is the same
 * person, and importing them twice is how a desk ends up with two histories
 * for one customer and answers half of one.
 *
 * A MAPPED CONTACT BECOMES A CUSTOMER, and nothing maps above it. There is no
 * organisation, no account and no company record to map to — the field map's
 * targets are a fixed list and an unmappable one is refused when it is
 * configured, not discovered at 3am.
 */
final class CustomerImport
{
    public function __construct(
        private readonly CustomerDirectory $customers,
        private readonly ErpSettings $settings,
        private readonly ExchangeLog $log,
    ) {}

    /**
     * @param  array<string, mixed>  $record  As the ERP sent it.
     * @return array{id: string, created: bool}
     */
    public function import(array $record): array
    {
        $mapped = $this->map($record);

        $name = trim((string) ($mapped['full_name'] ?? ''));
        $email = trim((string) ($mapped['email'] ?? ''));
        $phone = trim((string) ($mapped['phone'] ?? ''));

        if ($email === '' && $phone === '') {
            /*
             * Refused, and NOTHING is written. A partial customer record —
             * a name with no way to reach them — is worse than no record: it
             * appears in searches, gets picked in a duplicate check, and
             * cannot be contacted.
             */
            throw $this->refuse(
                'integrations.no_identifier',
                'That record has nobody in it',
                'An imported record needs an email address or a phone number. A customer nobody can be reached at is not a customer.',
            );
        }

        return $this->customers->resolveOrCreate(
            $email !== '' ? 'email' : 'phone',
            $email !== '' ? $email : $phone,
            $name,
            'erp_import',
        );
    }

    /**
     * Applies the configured field map, refusing a field that is not there.
     *
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>
     */
    private function map(array $record): array
    {
        $mapped = [];

        foreach ($this->settings->fieldMap() as $theirs => $ours) {
            if (! array_key_exists($theirs, $record)) {
                /*
                 * The exchange fails with a stated reason, and no partial
                 * record is written. A map that silently skipped a missing
                 * field would import half a customer and report success —
                 * and the half that is missing is whichever field the ERP
                 * renamed this morning.
                 */
                throw $this->refuse(
                    'integrations.unmapped_field',
                    'The record is missing a mapped field',
                    sprintf('The field map expects [%s], which this record does not have. Nothing was imported.', $theirs),
                    ['field' => $theirs, 'target' => $ours],
                );
            }

            $mapped[$ours] = $record[$theirs];
        }

        return $mapped;
    }

    /**
     * Refuses the record, and puts the reason where somebody will find it.
     *
     * The exception reaches the caller; the LOG ROW reaches the administrator
     * looking at the console tomorrow morning wondering why the overnight sync
     * imported nothing. Only one of those two people is actually going to be
     * there when this happens.
     *
     * @param  array<string, mixed>  $meta
     */
    private function refuse(string $code, string $title, string $detail, array $meta = []): ProblemException
    {
        $this->log->record(
            integration: ErpExchange::INTEGRATION,
            target: 'customer_import',
            status: ExchangeLog::FAILED,
            error: $detail,
            context: [...$meta, 'code' => $code],
        );

        return ProblemException::make($code, $title, 422, $detail, $meta);
    }
}
