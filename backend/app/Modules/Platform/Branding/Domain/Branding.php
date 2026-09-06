<?php

declare(strict_types=1);

namespace App\Modules\Platform\Branding\Domain;

use App\Modules\Platform\Support\Settings\SettingsRegistry;

/**
 * Exactly three values, reaching exactly four surfaces.
 *
 * THE THREE: a logo, a primary colour, and the portal/email header. There is
 * no application name, no separate sign-in logo, no palette, no typography or
 * spacing control, no preview workflow, no theme builder and no custom CSS —
 * not disabled, not deferred behind a flag: there is no field, no setting and
 * no upload for any of them.
 *
 * THE FOUR: the customer portal, the public web form, the live-chat widget and
 * outbound email. Everything a CUSTOMER sees, and nothing a colleague does.
 * The staff workspace and the console wear the default design system, and that
 * is structural rather than a review item — the staff layout root never reads
 * these values, so there is nothing there for a branded token to resolve
 * against.
 *
 * Branding alters PRESENTATION ONLY. No branded surface gains or loses a
 * control, and none renders a different field set. A brand that could change
 * what a customer can do would be a permission model wearing a colour picker.
 */
final class Branding
{
    public const LOGO_ATTACHMENT = 'branding.logo_attachment_id';

    public const PRIMARY_COLOUR = 'branding.primary_colour';

    public const HEADER = 'branding.header';

    /**
     * The backgrounds the primary colour will actually sit on.
     *
     * Fixed, and read from the token layer rather than guessed: these are the
     * surfaces the four branded screens are painted with. Checking against a
     * colour the product does not use would either refuse a colour that works
     * or accept one that does not, and only the second is discovered by a
     * customer.
     *
     * `--surface-raised` and `--surface-base` are both `#ffffff` today and
     * both are listed anyway — the check must keep meaning what it means when
     * one of them changes.
     *
     * @var array<string, string>
     */
    public const SURFACES = [
        'surface-base' => '#ffffff',
        'surface-raised' => '#ffffff',
        'surface-app' => '#f7f8fa',
        'surface-subtle' => '#fcfcfd',
        'surface-sunken' => '#f1f2f5',
    ];

    public function __construct(private readonly SettingsRegistry $settings) {}

    /**
     * What a branded surface needs, and nothing else.
     *
     * @return array{logo_url: string|null, primary_colour: string|null, header: string|null}
     */
    public function forCustomerSurfaces(): array
    {
        return [
            'logo_url' => $this->logoUrl(),
            'primary_colour' => $this->colour(),
            'header' => $this->header(),
        ];
    }

    private function colour(): ?string
    {
        $value = (string) $this->settings->get(self::PRIMARY_COLOUR);

        return trim($value) === '' ? null : $value;
    }

    private function header(): ?string
    {
        $value = (string) $this->settings->get(self::HEADER);

        return trim($value) === '' ? null : $value;
    }

    /**
     * The logo, served from the object store rather than this origin.
     *
     * It goes through the Story 3.2 attachment path — validated on upload,
     * scanned, and handed out as a storage URL. An image uploaded by an
     * administrator and then served from the application's own origin is a
     * file somebody else's browser executes in our security context.
     */
    private function logoUrl(): ?string
    {
        $id = $this->settings->get(self::LOGO_ATTACHMENT);

        if (! is_string($id) || trim($id) === '') {
            return null;
        }

        return \App\Modules\Platform\Attachments\Domain\Attachment::query()
            ->whereKey($id)
            ->value('id') === null ? null : "/api/v1/attachments/{$id}/download";
    }
}
