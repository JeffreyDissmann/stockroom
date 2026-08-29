<?php

declare(strict_types=1);

return [
    'title' => 'Vorschläge prüfen',
    'description' => 'Stockroom sieht sich nachts die Fotos deiner Objekte an und schlägt vor, was in ihren Einträgen fehlen könnte. Nichts wird geändert, bevor du zustimmst.',
    'empty' => 'Keine Vorschläge offen. Stockroom prüft nachts alle Fotos, die es noch nicht gesehen hat.',
    'current' => 'Jetzt',
    'proposed' => 'Vorschlag',
    'empty_field' => '(leer)',
    'accept' => 'Übernehmen',
    'reject' => 'Verwerfen',
    'stale' => 'Das Objekt wurde nach dem Vorschlag geändert – ein Übernehmen würde diese Änderung rückgängig machen. Verwirf ihn und lass die nächtliche Prüfung noch einmal schauen.',
    'stale_badge' => 'Objekt seitdem geändert',
    'already_reviewed' => 'Über diesen Vorschlag wurde bereits entschieden.',
    'from_model' => 'Aus :count Foto gelesen von :model|Aus :count Fotos gelesen von :model',
    'run' => 'Fotos jetzt ansehen',
    'run_none' => 'Stockroom hat alle Fotos angesehen.',
    'run_pending' => 'Stockroom hat die Fotos von :count Objekt noch nicht angesehen.|Stockroom hat die Fotos von :count Objekten noch nicht angesehen.',
    'running' => 'Fotos werden angesehen… :done / :total',
    'run_done' => 'Fertig – :total ausgewertet, :count neuer Vorschlag.|Fertig – :total ausgewertet, :count neue Vorschläge.',
    'run_failed_some' => ':count Objekt konnte nicht gelesen werden; es bleibt in der Warteschlange.|:count Objekte konnten nicht gelesen werden; sie bleiben in der Warteschlange.',
    'run_failed' => 'Die Auswertung ist fehlgeschlagen: :error',
];
