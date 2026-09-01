<?php

declare(strict_types=1);

/*
 * Server-rendered authentication strings: validation messages and the reset
 * email. On-screen strings live in frontend/messages/{en,ar}.json — see the
 * note in lang/en/notifications.php about the boundary.
 */

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many attempts. Please try again in :seconds seconds.',

    'password_policy' => [
        'invalid' => 'The password must be text.',
        'min_length' => 'The password must be at least :count characters.',
        'upper' => 'The password must contain an upper-case letter.',
        'lower' => 'The password must contain a lower-case letter.',
        'digit' => 'The password must contain a digit.',
        'symbol' => 'The password must contain a symbol.',
    ],

    'reset' => [
        'subject' => 'Reset your Ragab CRM password',
        'greeting' => 'Hello',
        'intro' => 'You are receiving this email because we received a password reset request for your account.',
        'action' => 'Reset password',
        'expiry' => 'This link expires in :count minutes and can be used once.',
        'ignore' => 'If you did not request a password reset, no further action is required.',
    ],
];
