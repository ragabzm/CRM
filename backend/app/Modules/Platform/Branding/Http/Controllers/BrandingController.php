<?php

declare(strict_types=1);

namespace App\Modules\Platform\Branding\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Branding\Domain\Branding;
use Illuminate\Http\JsonResponse;

/**
 * What the four branded surfaces read, and nothing more.
 *
 * PUBLIC, and it gives nothing away: three presentation values that appear on
 * pages anybody can already load. The portal, the public form and the widget
 * all need them before anybody has signed in — a brand that only appeared
 * after authentication would be a brand no customer sees at the moment they
 * arrive.
 *
 * There is no write method here. Branding is changed through the settings
 * console like every other setting, which is where the contrast refusal and
 * the audit entry already live — a second write path would be a second place
 * for a colour to get in without being measured.
 */
final class BrandingController extends Controller
{
    public function __construct(private readonly Branding $branding) {}

    /**
     * @response array{data: array{logo_url: string|null, primary_colour: string|null, header: string|null}}
     */
    public function show(): JsonResponse
    {
        return new JsonResponse(['data' => $this->branding->forCustomerSurfaces()]);
    }
}
