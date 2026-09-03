<?php

declare(strict_types=1);

namespace App\Modules\Portal;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

use Illuminate\Support\ServiceProvider;

/**
 * Registration seam for the Portal module.
 *
 * Story 1.1 ships the module as an empty scaffold: the provider exists so the
 * module owns its own migrations and bindings from day one, and so later
 * stories have a place to put them without touching shared bootstrap files.
 */
final class PortalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * Named limiters for the three unauthenticated doors.
         *
         * Per IP AND per address, because they stop different attacks: one
         * address hammered from a botnet is a password guess, and one IP
         * hammering many addresses is enumeration. A limiter keyed on only one
         * of them lets the other straight through.
         */
        RateLimiter::for('portal-login', static fn (Request $request) => [
            // Deliberately tight. A person who has forgotten their password
            // tries three or four times, not sixty.
            Limit::perMinute(5)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('email:'.strtolower((string) $request->input('email'))),
        ]);

        RateLimiter::for('portal-register', static fn (Request $request) => [
            // Registration is a once-ever act for a given person; anything
            // faster than this is a script.
            Limit::perMinute(3)->by('ip:'.$request->ip()),
        ]);

        RateLimiter::for('portal-password', static fn (Request $request) => [
            /*
             * Slower still: every request here sends an EMAIL, so an
             * unthrottled endpoint is a way to use this system to flood
             * somebody else's inbox.
             */
            Limit::perMinute(3)->by('ip:'.$request->ip()),
            Limit::perMinutes(15, 3)->by('email:'.strtolower((string) $request->input('email'))),
        ]);

        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    }
}
