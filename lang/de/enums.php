<?php

declare(strict_types=1);

return [
    'item_type' => [
        'room' => 'Raum',
        'container' => 'Behälter',
        'item' => 'Gegenstand',
    ],
    'custom_field_type' => [
        'text' => 'Text',
        'number' => 'Zahl',
        'date' => 'Datum',
        'boolean' => 'Ja / Nein',
        'url' => 'Link',
    ],
    'maintenance_schedule_type' => [
        'interval' => 'Nach Erledigung',
        'calendar' => 'Fester Rhythmus',
        'one_off' => 'Einmalig',
        'forecast' => 'Batterie-Prognose',
    ],
    'maintenance_interval_unit' => [
        'days' => 'Tage',
        'weeks' => 'Wochen',
        'months' => 'Monate',
        'years' => 'Jahre',
    ],

    'proposal_status' => [
        'pending' => 'Wartet auf Prüfung',
        'accepted' => 'Übernommen',
        'rejected' => 'Verworfen',
    ],

    'proposal_field' => [
        'description' => 'Beschreibung',
        'manufacturer' => 'Hersteller',
        'model_number' => 'Modellnummer',
        'serial_number' => 'Seriennummer',
    ],
];
