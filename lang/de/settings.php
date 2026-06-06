<?php

declare(strict_types=1);

return [
    'title' => 'Einstellungen',
    'subtitle' => 'Verwalte dein Profil und deine Kontoeinstellungen',

    'nav' => [
        'profile' => 'Profil',
        'password' => 'Passwort',
        'appearance' => 'Darstellung',
        'notifications' => 'Benachrichtigungen',
        'api_tokens' => 'API-Tokens',
    ],

    'language' => [
        'title' => 'Sprache',
        'description' => 'Wähle die Sprache der Benutzeroberfläche.',
    ],

    'appearance' => [
        'title' => 'Darstellung',
        'description' => 'Passe die Darstellung deines Kontos an',
        'light' => 'Hell',
        'dark' => 'Dunkel',
        'system' => 'System',
    ],

    'profile' => [
        'breadcrumb' => 'Profileinstellungen',
        'title' => 'Profilinformationen',
        'description' => 'Aktualisiere deinen Namen und deine E-Mail-Adresse',
        'name_placeholder' => 'Vollständiger Name',
        'email_label' => 'E-Mail-Adresse',
        'email_placeholder' => 'E-Mail-Adresse',
    ],

    'notifications' => [
        'breadcrumb' => 'Benachrichtigungseinstellungen',
        'title' => 'Benachrichtigungen',
        'description' => 'Wähle, welche E-Mails Stockroom dir schickt',
        'maintenance_digest_label' => 'Wartungs-Digest',
        'maintenance_digest_hint' => 'Schick mir täglich eine Zusammenfassung, wenn Wartungen überfällig oder bald fällig sind.',
    ],

    'password' => [
        'breadcrumb' => 'Passworteinstellungen',
        'title' => 'Passwort ändern',
        'description' => 'Verwende ein langes, zufälliges Passwort, damit dein Konto sicher bleibt',
        'current_label' => 'Aktuelles Passwort',
        'current_placeholder' => 'Aktuelles Passwort',
        'new_label' => 'Neues Passwort',
        'new_placeholder' => 'Neues Passwort',
        'confirm_label' => 'Passwort bestätigen',
        'confirm_placeholder' => 'Passwort bestätigen',
        'submit' => 'Passwort speichern',
    ],

    'api_tokens' => [
        'breadcrumb' => 'API-Tokens',
        'title' => 'API-Tokens',
        'description' => 'Erstelle Tokens für die Home-Assistant-Integration (und andere API-Clients). Ein Token wird nur einmal angezeigt – kopiere es jetzt.',
        'name_label' => 'Token-Name',
        'name_placeholder' => 'z. B. Home Assistant',
        'abilities_label' => 'Berechtigungen',
        'ability_read' => 'Lesen (Statistiken, Objekte, Suche)',
        'ability_write' => 'Schreiben (Objekte anlegen/ändern, Home-Assistant-Verknüpfungen setzen)',
        'create' => 'Token erstellen',
        'created_title' => 'Kopiere dein neues Token',
        'created_hint' => 'Dies ist das einzige Mal, dass dieses Token angezeigt wird. Bewahre es sicher auf.',
        'copy' => 'Kopieren',
        'copied' => 'Kopiert',
        'existing_title' => 'Aktive Tokens',
        'empty' => 'Noch keine Tokens.',
        'last_used' => 'Zuletzt verwendet :time',
        'never_used' => 'Nie verwendet',
        'created_at' => 'Erstellt :time',
        'revoke' => 'Widerrufen',
    ],

    'delete' => [
        'heading_title' => 'Konto löschen',
        'heading_description' => 'Lösche dein Konto und alle zugehörigen Daten',
        'warning' => 'Achtung',
        'warning_detail' => 'Bitte mit Vorsicht fortfahren – dies kann nicht rückgängig gemacht werden.',
        'button' => 'Konto löschen',
        'modal_title' => 'Möchtest du dein Konto wirklich löschen?',
        'modal_description' => 'Sobald dein Konto gelöscht ist, werden auch alle zugehörigen Ressourcen und Daten dauerhaft gelöscht. Bitte gib dein Passwort ein, um die dauerhafte Löschung deines Kontos zu bestätigen.',
        'password_label' => 'Passwort',
        'password_placeholder' => 'Passwort',
    ],
];
