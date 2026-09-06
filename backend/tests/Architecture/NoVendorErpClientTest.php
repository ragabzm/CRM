<?php

declare(strict_types=1);

namespace Tests\Architecture;

use App\Modules\Integrations\Contracts\ErpTransport;
use App\Modules\Integrations\Domain\ErpSettings;
use PHPUnit\Framework\TestCase;

/**
 * One generic REST adapter, not a catalogue of connectors.
 *
 * This is the rule most likely to be broken by somebody being helpful. The
 * first named connector always arrives with a good reason — "their API is a
 * bit different", "the SDK handles the pagination for us" — and it is never
 * the last, because the second customer runs a different ERP and now the
 * product has a vendor list to maintain, a vendor list to test, and a vendor
 * list to explain when one of them changes their API.
 *
 * ASM-13 is the reason the generic adapter is enough: there is no named ERP
 * yet. What ships is the adapter, its configuration, its test action and its
 * log. A connector catalogue would be a bet on which ERP the first customer
 * runs, placed before anybody has asked them.
 *
 * If a named ERP is ever genuinely required, deleting this file is the
 * deliberate act that says so — and it should be a conversation, not an import.
 */
final class NoVendorErpClientTest extends TestCase
{
    /**
     * Packages that would each BE a vendor client.
     *
     * Matched against the dependency manifest, which is where this arrives
     * first: a `composer require` is a smaller-looking decision than writing
     * an adapter, and it is a much larger one.
     *
     * @var list<string>
     */
    private const FORBIDDEN_PACKAGES = [
        'sap/',
        'odoo/',
        'netsuite',
        'dynamics',
        'quickbooks',
        'xero',
        'zoho',
        'salesforce',
        'forrest',
        'microsoft/dynamics',
        'oracle/',
    ];

    /**
     * Namespaces and class names that would each be the first named connector.
     *
     * @var list<string>
     */
    private const FORBIDDEN_SYMBOLS = [
        'SapTransport',
        'SapAdapter',
        'SapClient',
        'OdooTransport',
        'OdooClient',
        'NetSuite',
        'DynamicsClient',
        'QuickBooksClient',
        'XeroClient',
        'ZohoClient',
        'SalesforceClient',
        'ErpVendor',
        'VendorAdapter',
    ];

    public function test_the_dependency_manifest_names_no_erp_vendor(): void
    {
        $manifest = (string) file_get_contents(SourceScanner::basePath().'/composer.json');

        /** @var array{require?: array<string, string>, require-dev?: array<string, string>} $decoded */
        $decoded = json_decode($manifest, true, 512, JSON_THROW_ON_ERROR);

        $packages = array_keys([...$decoded['require'] ?? [], ...$decoded['require-dev'] ?? []]);

        $found = [];

        foreach ($packages as $package) {
            foreach (self::FORBIDDEN_PACKAGES as $vendor) {
                if (str_contains(strtolower((string) $package), $vendor)) {
                    $found[] = (string) $package;
                }
            }
        }

        $this->assertSame(
            [],
            $found,
            "A vendor ERP package is in the manifest. There is ONE generic REST adapter:\n"
            .implode("\n", $found),
        );
    }

    public function test_no_vendor_specific_adapter_exists_in_the_codebase(): void
    {
        $found = [];

        foreach (SourceScanner::moduleNames() as $module) {
            foreach (SourceScanner::phpFiles("app/Modules/{$module}") as $file) {
                // Code only, so a comment explaining why there is no SAP
                // adapter is not itself a SAP adapter.
                $code = SourceScanner::codeOnly($file);

                foreach (self::FORBIDDEN_SYMBOLS as $symbol) {
                    if (str_contains($code, $symbol)) {
                        $found[] = basename($file).' contains '.$symbol;
                    }
                }
            }
        }

        $this->assertSame([], $found, "A named vendor connector has appeared:\n".implode("\n", $found));
    }

    public function test_there_is_exactly_one_implementation_of_the_transport_port(): void
    {
        $implementations = [];

        foreach (SourceScanner::phpFiles('app/Modules/Integrations') as $file) {
            $code = SourceScanner::codeOnly($file);

            if (str_contains($code, 'implements ErpTransport')) {
                $implementations[] = basename($file, '.php');
            }
        }

        sort($implementations);

        /*
         * ONE. A second implementation here is what a connector catalogue
         * looks like on the day it starts — before it has a settings screen, a
         * list in the console and four vendors' pagination rules.
         *
         * Test doubles live in tests, where they are anonymous and cannot be
         * bound by a service provider.
         */
        $this->assertSame(['RestErpTransport'], $implementations);
        $this->assertTrue(interface_exists(ErpTransport::class));
    }

    public function test_the_field_map_cannot_target_anything_above_a_customer(): void
    {
        /*
         * A mapped contact becomes a customer, and NOTHING MAPS ABOVE IT.
         * There is no organisation, no account and no company record — and the
         * first step towards one is always a field map row that needs
         * somewhere to put "CompanyName".
         */
        foreach (ErpSettings::TARGETS as $target) {
            $this->assertDoesNotMatchRegularExpression(
                '/organisation|organization|company|account_name|parent/i',
                $target,
                "[{$target}] is a field on something above a customer. There is no such record.",
            );
        }
    }

    public function test_no_organisation_table_has_appeared(): void
    {
        $migrations = [];

        foreach (SourceScanner::moduleNames() as $module) {
            foreach (SourceScanner::phpFiles("app/Modules/{$module}/Database/Migrations") as $file) {
                $code = SourceScanner::codeOnly($file);

                if (preg_match("/Schema::create\('(organisations|organizations|companies|accounts)'/", $code) === 1) {
                    $migrations[] = basename($file);
                }
            }
        }

        $this->assertSame(
            [],
            $migrations,
            "A record above the customer has appeared:\n".implode("\n", $migrations),
        );
    }
}
