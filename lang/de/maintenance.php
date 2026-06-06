<?php

declare(strict_types=1);

return [
    'page' => [
        'title' => 'Wartung',
        'subtitle' => 'Alle Wartungspläne des Haushalts, nächste Fälligkeit zuerst.',
        'empty' => 'Keine Wartungsaufgaben für diesen Filter.',
    ],
    'filters' => [
        'all' => 'Alle',
        'overdue' => 'Überfällig',
        'due_soon' => 'Bald fällig',
    ],

    'section_title' => 'Wartung',
    'add_task' => 'Aufgabe anlegen',
    'log_entry' => 'Eintrag erfassen',
    'empty' => 'Noch keine Wartungspläne.',
    'history_title' => 'Wartungsverlauf',
    'history_empty' => 'Noch nichts erfasst.',
    'one_time' => 'Einmalig',
    'by_name' => 'von :name',
    'last_done' => 'Zuletzt erledigt: :date',
    'mark_done' => 'Erledigt',
    'skip' => 'Fälligkeit überspringen',
    'delete_task' => 'Aufgabe löschen',
    'delete_task_confirm' => '":title" löschen? Die Verlaufseinträge bleiben erhalten.',
    'delete_entry_confirm' => 'Diesen Verlaufseintrag löschen?',

    'due' => [
        'today' => 'Heute fällig',
        'in_days' => 'Fällig in :count Tag|Fällig in :count Tagen',
        'overdue' => ':count Tag überfällig|:count Tage überfällig',
        'none' => 'Kein Fälligkeitsdatum',
    ],

    'dialog' => [
        'create_title' => 'Wartungsaufgabe anlegen',
        'edit_title' => 'Wartungsaufgabe bearbeiten',
        'description' => 'Wartung für :name planen.',
        'title_label' => 'Titel',
        'title_placeholder' => 'z. B. Batterien wechseln (2× AA)',
        'notes_label' => 'Notizen',
        'schedule_type_label' => 'Zeitplan',
        'type_hint' => [
            'interval' => 'Die nächste Fälligkeit zählt ab dem Tag der tatsächlichen Erledigung.',
            'calendar' => 'Feste Kalendertermine, unabhängig von der letzten Erledigung.',
            'one_off' => 'Ein einzelnes Fälligkeitsdatum; die Aufgabe wird nach Erledigung archiviert.',
        ],
        'interval_label' => 'Wiederholen alle',
        'preset_label' => 'Regel',
        'preset_every' => 'Regelmäßiger Abstand',
        'preset_yearly_on' => 'Jährlich an einem Datum',
        'preset_nth_weekday' => 'Wochentag im Monat',
        'month_label' => 'Monat',
        'day_label' => 'Tag',
        'ordinal_label' => 'Welcher',
        'weekday_label' => 'Wochentag',
        'every_month_option' => 'Jeden Monat',
        'due_date_label' => 'Fällig am',
        'lead_label' => 'Erinnerung Tage vor Fälligkeit',
        'custom_rule_note' => 'Diese Aufgabe nutzt eine eigene Regel (:summary); beim Speichern bleibt sie unverändert.',
        'submit_create' => 'Aufgabe anlegen',
        'submit_save' => 'Änderungen speichern',
    ],

    'done_dialog' => [
        'title' => '":title" als erledigt markieren',
        'description' => 'Erfasst eine Erledigung und verschiebt die nächste Fälligkeit.',
        'date_label' => 'Erledigt am',
        'notes_label' => 'Notizen',
        'cost_label' => 'Kosten (:code)',
        'submit' => 'Als erledigt markieren',
    ],

    'entry_dialog' => [
        'title' => 'Wartung erfassen',
        'description' => 'Reparatur oder einmalige Wartung für :name festhalten.',
        'date_label' => 'Erledigt am',
        'notes_label' => 'Was wurde gemacht?',
        'cost_label' => 'Kosten (:code)',
        'submit' => 'Eintrag erfassen',
    ],

    'schedule' => [
        'every' => [
            'days' => 'Jeden Tag|Alle :count Tage',
            'weeks' => 'Jede Woche|Alle :count Wochen',
            'months' => 'Jeden Monat|Alle :count Monate',
            'years' => 'Jedes Jahr|Alle :count Jahre',
        ],
        'interval' => ':every nach Erledigung',
        'calendar_every' => ':every (fest)',
        'yearly_on' => 'Jährlich am :date',
        'yearly_on_date_format' => 'j. F',
        'nth_weekday_monthly' => 'Jeden :ordinal :weekday im Monat',
        'nth_weekday_yearly' => 'Jeden :ordinal :weekday im :month',
        'one_off' => 'Einmalig',
        'custom' => 'Eigene Regel',
        'ordinals' => [
            '1' => 'ersten',
            '2' => 'zweiten',
            '3' => 'dritten',
            '4' => 'vierten',
            '-1' => 'letzten',
        ],
        'weekdays' => [
            'MO' => 'Montag',
            'TU' => 'Dienstag',
            'WE' => 'Mittwoch',
            'TH' => 'Donnerstag',
            'FR' => 'Freitag',
            'SA' => 'Samstag',
            'SU' => 'Sonntag',
        ],
    ],
];
