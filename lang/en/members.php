<?php

declare(strict_types=1);

return [
    'invites_title' => 'Invite links',
    'invites_desc' => 'Create a single-use link and share it however you like — or enter an email address and Stockroom sends it. It expires after 7 days, and the person sets their own name, email, and password when they join.',
    'label_placeholder' => 'Label (optional, e.g. For Anna)',
    'email_placeholder' => 'Email the invite to… (optional)',
    'create' => 'Create invite link',
    'sent_to' => 'sent to :email',
    'resend' => 'Send again',
    'mail_sent' => 'Invite emailed.',
    'mail_failed' => 'The invite was created, but the email could not be sent — copy the link instead.',

    'mail' => [
        'subject' => ':name invited you to Stockroom',
        'intro' => ':name invited you to the household inventory on Stockroom. Click the button to pick your own name, email and password.',
        'action' => 'Join Stockroom',
        'expiry' => 'This link is single-use and expires in :days days.',
    ],
    'none' => 'No active invite links.',
    'link' => 'Invite link',
    'expires' => 'Expires :when',
    'from' => 'from :name',
    'revoke_title' => 'Revoke link',
    'revoke_confirm' => 'Revoke this invite link? It will stop working immediately.',
    'people_title' => 'People',
    'people_desc' => 'Everyone with access to this home inventory.',
    'you' => '(you)',
    'joined' => 'Joined :when',
    'role_admin' => 'Admin',
    'role_member' => 'Member',
    'make_admin' => 'Make admin',
    'remove_admin' => 'Remove admin',
    'remove' => 'Remove',
    'remove_confirm' => 'Remove :name? Their login and pending invites are deleted; the inventory is kept.',
];
