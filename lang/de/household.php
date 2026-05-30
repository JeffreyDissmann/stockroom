<?php

declare(strict_types=1);

return [
    'title' => 'Haushalt',
    'subtitle' => 'Einstellungen für das gesamte Inventar des Haushalts',

    'nav' => [
        'custom_fields' => 'Eigene Felder',
        'backup' => 'Sicherung & Import',
        'search_index' => 'Suchindex',
        'members' => 'Mitglieder',
        'preferences' => 'Einstellungen',
    ],

    'preferences' => [
        'description' => 'Haushaltsweite Einstellungen, die das Verhalten von Stockroom beeinflussen.',
        'box_tag' => 'Karton-Schlagwort',
        'box_tag_none' => '(keines – Kartons nicht automatisch markieren)',
        'box_tag_help' => 'Wenn du über „Karton für diesen Gegenstand erstellen" ein Verpackungs-Inventar anlegst, wird dieses Schlagwort automatisch zugewiesen. So findest du alle aufbewahrten Originalverpackungen an einer Stelle.',
        'paperless_parent' => 'Ziel für Paperless-Importe',
        'paperless_parent_none' => '(keines – auf oberster Ebene anlegen)',
        'paperless_parent_help' => 'Wenn Paperless-ngx ein Dokument an Stockroom übergibt, landen die daraus extrahierten Gegenstände in diesem Raum oder Behälter. Du kannst sie jederzeit verschieben – das hier legt nur fest, wo sie zuerst erscheinen.',
        'paperless_parent_search' => 'Räume und Behälter durchsuchen…',
        'paperless_parent_no_match' => 'Keine passenden Räume oder Behälter.',
        'paperless_relink' => 'Paperless-Verknüpfungen reparieren',
        'paperless_relink_action' => 'Alle Dokumente erneut verknüpfen',
        'paperless_relink_help' => 'Geht jedes Paperless-Dokument durch, mit dem ein Gegenstand verknüpft ist, und setzt das Stockroom-Schlagwort und die Rück-URL auf der Paperless-Seite neu. Nützlich nach Änderungen an APP_URL oder wenn das Schlagwort manuell entfernt wurde. Läuft im Hintergrund – ein Queue-Worker muss aktiv sein.',
        'paperless_relink_confirm' => 'Stockroom-Schlagwort und Rück-URL auf jedem verknüpften Paperless-Dokument erneut setzen?',
        'paperless_relink_none' => 'Keine Dokumente zum Verknüpfen.',
        'paperless_relink_progress' => 'Verknüpfe… :done / :total',
        'paperless_relink_done' => ':count Dokument neu verknüpft.|:count Dokumente neu verknüpft.',
        'paperless_relink_failed_count' => ':count fehlgeschlagen.|:count fehlgeschlagen.',
        'paperless_relink_failed' => 'Re-Link-Job fehlgeschlagen: :error',
    ],

    'import' => [
        'title' => 'Aus Homebox importieren',
        'description' => 'Hole Orte, Gegenstände, Fotos, Schlagwörter und eigene Felder aus einer laufenden Homebox-Instanz. Ein erneuter Lauf aktualisiert vorhandene Gegenstände, statt sie zu duplizieren.',
        'url' => 'Homebox-URL',
        'email' => 'E-Mail',
        'password' => 'Passwort',
        'note' => 'Deine Zugangsdaten werden einmalig für ein Token verwendet und nie gespeichert. Der Import läuft im Hintergrund – ein Queue-Worker muss laufen.',
        'submit' => 'Verbinden & importieren',
        'discovering' => 'Verbinde mit Homebox und ermittle die Gegenstände… Bei großen Instanzen kann das eine Minute dauern.',
        'progress' => 'Importiere… :done / :total',
        'done' => ':entities Einträge importiert (:created neu, :updated aktualisiert) und :images Fotos',
        'skipped' => ' (:count nicht unterstützte(s) Foto(s) übersprungen)',
        'failed_title' => 'Import fehlgeschlagen',
        'failed' => ':error',
    ],

    'search_index' => [
        'description' => 'Baue den Volltext-Suchindex für alle Gegenstände neu auf. Nützlich nach einer Massenänderung oder um semantische Such-Embeddings (neu) zu erzeugen.',
        'count' => ':count Gegenstand zu indexieren.|:count Gegenstände zu indexieren.',
        'semantic_on' => 'Die semantische Suche ist aktiv – unveränderte Gegenstände nutzen zwischengespeicherte Embeddings, daher sind erneute Läufe schnell.',
        'semantic_off' => 'Die semantische Suche ist aus (nur Stichwortsuche).',
        'worker_note' => 'Der Neuaufbau läuft im Hintergrund – ein Queue-Worker muss laufen.',
        'rebuild' => 'Suchindex neu aufbauen',
        'progress' => 'Indexiere… :done / :total',
        'done' => 'Fertig – :count Gegenstand indexiert.|Fertig – :count Gegenstände indexiert.',
        'failed' => 'Neuindexierung fehlgeschlagen: :error',
    ],

    'backup' => [
        'description' => 'Lade dein gesamtes Inventar – Gegenstände, Schlagwörter und Originalfotos – als einzelnes .zip-Archiv herunter oder stelle eines wieder her. Abgeleitete Bildgrößen werden bei der Wiederherstellung automatisch neu erzeugt.',
        'download' => 'Sicherung herunterladen',
        'restore_note' => 'Beim Wiederherstellen werden Gegenstände, Schlagwörter und Bilder mit übereinstimmenden IDs aktualisiert und alles Neue hinzugefügt. Andere Gegenstände bleiben unberührt.',
        'restore' => 'Sicherung wiederherstellen',
        'result' => ':items Gegenstand/Gegenstände, :tags Schlagwort/Schlagwörter und :images Bild(er) wiederhergestellt.',
    ],

    'danger' => [
        'title' => 'Gefahrenzone',
        'description' => 'Lösche das Inventar dauerhaft – jeden Gegenstand und jedes Foto. Erstelle vorher eine Sicherung; dies kann nicht rückgängig gemacht werden.',
        'include_tags' => 'Auch alle Schlagwörter löschen',
        'include_custom_fields' => 'Auch alle eigenen Felder löschen',
        'include_activity' => 'Auch das Aktivitätsprotokoll leeren',
        'wipe' => 'Inventar leeren',
        'done' => 'Inventar geleert.',
        'confirm' => 'Dies löscht dauerhaft jeden Gegenstand und jedes Foto:tail. Dies kann nicht rückgängig gemacht werden. Fortfahren?',
        'extra_tags' => 'alle Schlagwörter',
        'extra_custom_fields' => 'alle eigenen Felder',
        'extra_activity' => 'das Aktivitätsprotokoll',
        'and' => 'und',
    ],

    'custom_fields' => [
        'description' => 'Definiere zusätzliche typisierte Felder (z. B. Farbe, Spannung, Kauf-URL), die bei jedem Gegenstand ausgefüllt werden können.',
        'name_placeholder' => 'Feldname',
        'searchable' => 'Durchsuchbar',
        'searchable_title' => 'Werte dieses Feldes in die Suche einbeziehen',
        'add' => 'Feld hinzufügen',
        'empty' => 'Noch keine eigenen Felder.',
        'not_searchable' => 'Nicht durchsuchbar',
        'included' => 'In der Suche enthalten',
        'excluded' => 'Von der Suche ausgeschlossen',
        'system' => 'System',
        'delete_confirm' => 'Das Feld „:name“ löschen? Seine Werte werden bei jedem Gegenstand entfernt.',
    ],
];
