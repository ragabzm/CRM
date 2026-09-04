<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Middleware;

use Closure;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers in the language the reader is reading.
 *
 * Nothing set the application locale per request, so `app()->getLocale()` was
 * always the config default. Notifications escaped it — `HasLocalePreference`
 * makes Laravel wrap each send in the recipient's language — and everything
 * else did not. The visible symptom: an agent switching the interface to
 * Arabic got Arabic chrome, Arabic column headers, Arabic statuses, and an
 * English category name in the next column, because the one string the SERVER
 * chose the wording of was picked from the account's stored preference rather
 * than from the screen in front of them.
 *
 * Order matters, and it is deliberate:
 *
 *   1. `Accept-Language`, which the client sends from the locale switcher. It
 *      is what the person is looking at RIGHT NOW.
 *   2. The signed-in account's stored preference, for anything that reaches
 *      the API without a header — a webhook replay, a console command hitting
 *      an internal route.
 *   3. The configured default.
 *
 * The header wins over the stored preference on purpose. Someone who switches
 * the interface to Arabic for one conversation has not changed their account;
 * they have changed what they want to read, and only for now.
 */
final class SetLocaleFromRequest
{
    /** @var list<string> */
    private const SUPPORTED = ['en', 'ar'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = self::fromHeader($request) ?? self::fromAccount($request);

        if ($locale !== null) {
            App::setLocale($locale);
        }

        return $next($request);
    }

    private static function fromHeader(Request $request): ?string
    {
        /*
         * Only the primary tag, and only from an allow-list. `Accept-Language`
         * is caller-supplied text; feeding it to `setLocale` unchecked would
         * let a request name a translation file that does not exist, or worse,
         * a path.
         */
        $header = (string) $request->headers->get('Accept-Language', '');

        if ($header === '') {
            return null;
        }

        $primary = strtolower(trim(explode(',', $header)[0]));
        $primary = explode('-', explode(';', $primary)[0])[0];

        return in_array($primary, self::SUPPORTED, true) ? $primary : null;
    }

    private static function fromAccount(Request $request): ?string
    {
        // Whichever guard answered — staff or portal. Both implement the
        // interface, and neither is reached from here by name.
        $user = $request->user();

        if ($user instanceof HasLocalePreference) {
            $preferred = $user->preferredLocale();

            return in_array($preferred, self::SUPPORTED, true) ? $preferred : null;
        }

        return null;
    }
}
