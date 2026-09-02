<?php

declare(strict_types=1);

namespace App\Modules\Security;

use App\Modules\Platform\Http\ProblemDetails;
use App\Modules\Platform\Http\ProblemDetailsHandler;
use App\Modules\Security\Contracts\DepartmentUsageProbe;
use App\Modules\Security\Contracts\NoDepartmentUsage;
use App\Modules\Security\Domain\Capabilities;
use App\Modules\Security\Contracts\DepartmentDirectory;
use App\Modules\Security\Domain\EloquentDepartmentDirectory;
use App\Modules\Security\Domain\Roles;
use App\Modules\Security\Events\StaffAuthAttempted;
use App\Modules\Security\Listeners\LogStaffAuthAttempt;
use App\Modules\Security\Listeners\RecordStaffAuthAttempt;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * T1. Owns staff authentication: guards, rate limits, and the audit hook.
 */
final class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Published for higher tiers that group by department without needing
        // to know how one is stored.
        $this->app->singleton(DepartmentDirectory::class, EloquentDepartmentDirectory::class);

        /*
         * The default answer to "is anything using this department?" is "no".
         * The Tickets module rebinds this with a real query when it is present;
         * without a default, Security could not deactivate a department — or be
         * tested — before Tickets exists.
         */
        $this->app->bind(DepartmentUsageProbe::class, NoDepartmentUsage::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        $this->registerRateLimiters();
        $this->registerSessionExpiryProblem();
        $this->registerCapabilityGates();

        Event::listen(StaffAuthAttempted::class, LogStaffAuthAttempt::class);
        // Both, not either: the log line is for operations, the audit row is
        // for the review that happens months later.
        Event::listen(StaffAuthAttempted::class, RecordStaffAuthAttempt::class);
    }

    /**
     * Named limiters for the two endpoints an attacker will hammer.
     *
     * Keyed on email AND ip together, not either alone: keying on IP only lets
     * one attacker behind a NAT lock out an office, and keying on email only
     * lets a distributed attacker lock a named person out of their own account.
     * The pair throttles the actual attack shape — many guesses at one account —
     * while leaving everyone else unaffected.
     */
    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by($this->throttleKey($request));
        });

        RateLimiter::for('password-reset', function (Request $request): Limit {
            // Three in ten minutes: requesting a link is a rare, deliberate act,
            // and each one sends mail to a real person's inbox.
            return Limit::perMinutes(10, 3)->by($this->throttleKey($request));
        });

        RateLimiter::for('password-reset-confirm', function (Request $request): Limit {
            /*
             * Redeeming a token is a DIFFERENT operation from requesting one and
             * gets its own, more generous budget.
             *
             * Sharing one 3-per-10-minutes budget locks a user out of finishing
             * their own reset: request the link (1), mistype the new password
             * twice (2, 3), and the fourth attempt — the correct one — is
             * refused. Meanwhile the thing a limit would defend against here,
             * brute-forcing a 64-character random token, is infeasible at any
             * rate. So this limit exists to bound abuse, not to police typos.
             */
            return Limit::perMinutes(10, 15)->by($this->throttleKey($request));
        });
    }

    /**
     * Narrows a generic 401 into "your session ended".
     *
     * On a session-cookie API a 401 almost always means the session lapsed
     * rather than that the caller never had one, and the client needs to tell
     * those apart: an expired session should bounce to sign-in remembering where
     * the reader was, a genuinely anonymous call should not.
     *
     * Registered through Platform's mapper seam rather than added to Platform's
     * own table, because `security.*` is this module's vocabulary and T0 must
     * not name a higher tier's codes.
     */
    private function registerSessionExpiryProblem(): void
    {
        $this->app->afterResolving(
            ProblemDetailsHandler::class,
            function (ProblemDetailsHandler $handler): void {
                $handler->extend(function (Throwable $e, Request $request): ?ProblemDetails {
                    if (! $e instanceof AuthenticationException) {
                        return null;
                    }

                    // Only for this module's surface; another module's 401 keeps
                    // the platform default.
                    if (! $request->is('api/*/auth/*', 'api/*/profile', 'api/*/profile/*')) {
                        return null;
                    }

                    return new ProblemDetails(
                        'security.session_expired',
                        'Your session has ended.',
                        401,
                        'Sign in again to continue.',
                    );
                });
            },
        );
    }

    /**
     * Makes every capability answerable through Gate as well as through the
     * permission table, so `authorize()`, `@can` and `can:` middleware all
     * agree on one answer.
     */
    private function registerCapabilityGates(): void
    {
        /*
         * Administrator holds everything IMPLICITLY rather than by being
         * granted each permission row.
         *
         * The difference matters: a capability added in a later story is one an
         * administrator already has, with no seeder re-run and no window where
         * the person responsible for fixing permissions is the one locked out.
         *
         * Returning null (not false) for everyone else hands the decision back
         * to the normal gate chain instead of short-circuiting it.
         */
        Gate::before(function (?Authenticatable $user, string $ability): ?bool {
            if ($user === null) {
                return null;
            }

            return method_exists($user, 'hasRole') && $user->hasRole(Roles::ADMINISTRATOR)
                ? true
                : null;
        });

        foreach (Capabilities::all() as $capability) {
            Gate::define($capability, function (Authenticatable $user) use ($capability): bool {
                return method_exists($user, 'hasPermissionTo')
                    && $user->hasPermissionTo($capability, 'web');
            });
        }
    }

    private function throttleKey(Request $request): string
    {
        $email = $request->input('email');
        $email = is_string($email) ? mb_strtolower(trim($email)) : 'anonymous';

        return $email.'|'.($request->ip() ?? 'unknown-ip');
    }
}
