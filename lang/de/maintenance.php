<?php

declare(strict_types=1);

return [
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
