<?php

declare(strict_types=1);

return [
    'schedule' => [
        // trans_choice per unit so "every month" reads naturally instead
        // of "every 1 months".
        'every' => [
            'days' => 'Every day|Every :count days',
            'weeks' => 'Every week|Every :count weeks',
            'months' => 'Every month|Every :count months',
            'years' => 'Every year|Every :count years',
        ],
        'interval' => ':every after completion',
        'calendar_every' => ':every (fixed)',
        'yearly_on' => 'Yearly on :date',
        'yearly_on_date_format' => 'j F',
        'nth_weekday_monthly' => 'Every :ordinal :weekday of the month',
        'nth_weekday_yearly' => 'Every :ordinal :weekday in :month',
        'one_off' => 'Once',
        'custom' => 'Custom schedule',
        'ordinals' => [
            '1' => 'first',
            '2' => 'second',
            '3' => 'third',
            '4' => 'fourth',
            '-1' => 'last',
        ],
        'weekdays' => [
            'MO' => 'Monday',
            'TU' => 'Tuesday',
            'WE' => 'Wednesday',
            'TH' => 'Thursday',
            'FR' => 'Friday',
            'SA' => 'Saturday',
            'SU' => 'Sunday',
        ],
    ],
];
