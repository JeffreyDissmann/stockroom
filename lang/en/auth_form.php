<?php

declare(strict_types=1);

/*
| UI copy for the auth screens (login, register, password reset, etc.).
| Kept out of Laravel's reserved `auth.php` namespace so the framework's
| backend strings ("These credentials do not match…") never ship to the
| JS layer — this group is exposed to the frontend via $t, auth.php is not.
*/

return [
    // Shared form field labels + placeholders across the auth screens.
    'fields' => [
        'name' => 'Name',
        'email' => 'Email address',
        'password' => 'Password',
        'confirm_password' => 'Confirm password',
    ],
    'placeholders' => [
        'name' => 'Full name',
        'email' => 'email@example.com',
        'password' => 'Password',
        'confirm_password' => 'Confirm password',
    ],

    'login' => [
        'meta' => 'Log in',
        'title' => 'Log in to your account',
        'description' => 'Enter your email and password below to log in',
        'forgot' => 'Forgot password?',
        'remember' => 'Remember me',
        'submit' => 'Log in',
    ],
    'register' => [
        'meta' => 'Create account',
        'title' => 'Create your account',
        'description' => 'Set up your account to join this home inventory.',
        'description_invited' => ':name invited you to share this home inventory.',
        'submit' => 'Create account',
        'have_account' => 'Already have an account?',
        'log_in' => 'Log in',
    ],
    'forgot' => [
        'meta' => 'Forgot password',
        'title' => 'Forgot password',
        'description' => 'Enter your email to receive a password reset link',
        'submit' => 'Email password reset link',
        'return_to' => 'Or, return to',
        'log_in' => 'log in',
    ],
    'reset' => [
        'meta' => 'Reset password',
        'title' => 'Reset password',
        'description' => 'Please enter your new password below',
        'submit' => 'Reset password',
    ],
    'confirm' => [
        'meta' => 'Confirm password',
        'title' => 'Confirm your password',
        'description' => 'This is a secure area of the application. Please confirm your password before continuing.',
        'submit' => 'Confirm password',
    ],
    'invite_invalid' => [
        'meta' => 'Invite not valid',
        'title' => 'Invite link not valid',
        'description' => "This invite link has expired, was already used, or doesn't exist. Ask whoever invited you for a fresh link.",
        'have_account' => 'Already have an account?',
        'log_in' => 'Log in',
    ],
];
