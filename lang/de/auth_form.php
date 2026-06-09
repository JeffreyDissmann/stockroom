<?php

declare(strict_types=1);

/*
| Deutsche UI-Texte für die Auth-Seiten. Bewusst außerhalb von Laravels
| reserviertem `auth.php`-Namespace, damit die Framework-Backend-Strings
| nicht ins JS gelangen. Siehe lang/en/auth_form.php.
*/

return [
    'fields' => [
        'name' => 'Name',
        'email' => 'E-Mail-Adresse',
        'password' => 'Passwort',
        'confirm_password' => 'Passwort bestätigen',
    ],
    'placeholders' => [
        'name' => 'Vollständiger Name',
        'email' => 'name@beispiel.de',
        'password' => 'Passwort',
        'confirm_password' => 'Passwort bestätigen',
    ],

    'login' => [
        'meta' => 'Anmelden',
        'title' => 'Bei deinem Konto anmelden',
        'description' => 'Gib unten deine E-Mail-Adresse und dein Passwort ein, um dich anzumelden',
        'forgot' => 'Passwort vergessen?',
        'remember' => 'Angemeldet bleiben',
        'submit' => 'Anmelden',
    ],
    'register' => [
        'meta' => 'Konto erstellen',
        'title' => 'Konto erstellen',
        'description' => 'Richte dein Konto ein, um diesem Haushaltsinventar beizutreten.',
        'description_invited' => ':name hat dich eingeladen, dieses Haushaltsinventar mitzunutzen.',
        'submit' => 'Konto erstellen',
        'have_account' => 'Du hast bereits ein Konto?',
        'log_in' => 'Anmelden',
    ],
    'forgot' => [
        'meta' => 'Passwort vergessen',
        'title' => 'Passwort vergessen',
        'description' => 'Gib deine E-Mail-Adresse ein, um einen Link zum Zurücksetzen zu erhalten',
        'submit' => 'Link zum Zurücksetzen senden',
        'return_to' => 'Oder zurück zur',
        'log_in' => 'Anmeldung',
    ],
    'reset' => [
        'meta' => 'Passwort zurücksetzen',
        'title' => 'Passwort zurücksetzen',
        'description' => 'Bitte gib unten dein neues Passwort ein',
        'submit' => 'Passwort zurücksetzen',
    ],
    'confirm' => [
        'meta' => 'Passwort bestätigen',
        'title' => 'Passwort bestätigen',
        'description' => 'Dies ist ein geschützter Bereich. Bitte bestätige dein Passwort, um fortzufahren.',
        'submit' => 'Passwort bestätigen',
    ],
    'invite_invalid' => [
        'meta' => 'Einladung ungültig',
        'title' => 'Einladungslink ungültig',
        'description' => 'Dieser Einladungslink ist abgelaufen, wurde bereits verwendet oder existiert nicht. Bitte hol dir bei der Person, die dich eingeladen hat, einen neuen Link.',
        'have_account' => 'Du hast bereits ein Konto?',
        'log_in' => 'Anmelden',
    ],
];
