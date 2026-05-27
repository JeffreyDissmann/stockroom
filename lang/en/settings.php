<?php

declare(strict_types=1);

return [
    'title' => 'Settings',
    'subtitle' => 'Manage your profile and account settings',

    'nav' => [
        'profile' => 'Profile',
        'password' => 'Password',
        'appearance' => 'Appearance',
    ],

    'language' => [
        'title' => 'Language',
        'description' => 'Choose the language used across the interface.',
    ],

    'appearance' => [
        'title' => 'Appearance settings',
        'description' => "Update your account's appearance settings",
        'light' => 'Light',
        'dark' => 'Dark',
        'system' => 'System',
    ],

    'profile' => [
        'breadcrumb' => 'Profile settings',
        'title' => 'Profile information',
        'description' => 'Update your name and email address',
        'name_placeholder' => 'Full name',
        'email_label' => 'Email address',
        'email_placeholder' => 'Email address',
    ],

    'password' => [
        'breadcrumb' => 'Password settings',
        'title' => 'Update password',
        'description' => 'Ensure your account is using a long, random password to stay secure',
        'current_label' => 'Current password',
        'current_placeholder' => 'Current password',
        'new_label' => 'New password',
        'new_placeholder' => 'New password',
        'confirm_label' => 'Confirm password',
        'confirm_placeholder' => 'Confirm password',
        'submit' => 'Save password',
    ],

    'delete' => [
        'heading_title' => 'Delete account',
        'heading_description' => 'Delete your account and all of its resources',
        'warning' => 'Warning',
        'warning_detail' => 'Please proceed with caution, this cannot be undone.',
        'button' => 'Delete account',
        'modal_title' => 'Are you sure you want to delete your account?',
        'modal_description' => 'Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.',
        'password_label' => 'Password',
        'password_placeholder' => 'Password',
    ],
];
