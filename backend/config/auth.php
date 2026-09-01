<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        /*
         * TWO IDENTITY SPACES, TWO GUARDS, TWO COOKIES.
         *
         * Staff and portal customers are separate populations with separate
         * tables — there is no `is_staff` column and no shared table with a
         * discriminator. A credential that authenticates against one provider is
         * meaningless to the other, because the other simply cannot see the row.
         *
         * That is the whole reason for the split: a discriminator column makes
         * "am I staff?" a value someone can get wrong, while two tables make the
         * question unanswerable in the wrong direction.
         *
         * Enforced by tests/Feature/Security/GuardIsolationTest.php.
         */
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'portal' => [
            'driver' => 'session',
            'provider' => 'portal_accounts',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        /*
         * Referenced by class-string rather than imported: config/ is outside
         * app/Modules, so this is the one place the two identity spaces meet
         * without either module depending on the other.
         */
        'portal_accounts' => [
            'driver' => 'eloquent',
            'model' => \App\Modules\Portal\Domain\PortalAccount::class,
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],

        'portal_accounts' => [
            'provider' => 'portal_accounts',
            'table' => 'portal_password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Policy
    |--------------------------------------------------------------------------
    |
    | Read by App\Modules\Security\Rules\PasswordPolicy. Every flow that sets a
    | password — reset, change — goes through that one rule, so the policy is a
    | configuration value rather than a number repeated across form requests.
    |
    | Length does more for strength than any single character class, which is why
    | the default is 12 with symbols optional: a symbol requirement mostly buys
    | a trailing "!" and a password nobody can type on a phone.
    |
    */

    'password_policy' => [
        'min_length' => (int) env('AUTH_PASSWORD_MIN_LENGTH', 12),
        'require_upper' => (bool) env('AUTH_PASSWORD_REQUIRE_UPPER', true),
        'require_lower' => (bool) env('AUTH_PASSWORD_REQUIRE_LOWER', true),
        'require_digit' => (bool) env('AUTH_PASSWORD_REQUIRE_DIGIT', true),
        'require_symbol' => (bool) env('AUTH_PASSWORD_REQUIRE_SYMBOL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Staff Session
    |--------------------------------------------------------------------------
    |
    | Exposed through GET /api/auth/session so the frontend can warn before a
    | session lapses rather than discovering it on the next request. Mirrors
    | SESSION_LIFETIME; kept as its own key so the staff timeout can diverge from
    | the framework default without touching session.php.
    |
    */

    'staff' => [
        'inactivity_minutes' => (int) env('STAFF_INACTIVITY_MINUTES', env('SESSION_LIFETIME', 120)),
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
