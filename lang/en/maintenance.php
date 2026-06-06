<?php

declare(strict_types=1);

return [
    'page' => [
        'title' => 'Maintenance',
        'subtitle' => 'Every schedule across the household, soonest due first.',
        'empty' => 'No maintenance tasks match this filter.',
    ],
    'filters' => [
        'all' => 'All',
        'overdue' => 'Overdue',
        'due_soon' => 'Due soon',
    ],

    'digest' => [
        'subject' => 'Stockroom: :count maintenance task needs attention|Stockroom: :count maintenance tasks need attention',
        'intro' => 'The following maintenance is due in your household.',
        'overdue_heading' => 'Overdue',
        'due_soon_heading' => 'Due soon',
        'task_line' => '- :task — :item (:due)',
        'action' => 'Open maintenance overview',
    ],

    'section_title' => 'Maintenance',
    'add_task' => 'Add task',
    'log_entry' => 'Log entry',
    'empty' => 'No maintenance schedules yet.',
    'history_title' => 'Maintenance history',
    'history_empty' => 'Nothing recorded yet.',
    'one_time' => 'One-time',
    'by_name' => 'by :name',
    'last_done' => 'Last done: :date',
    'mark_done' => 'Done',
    'skip' => 'Skip occurrence',
    'delete_task' => 'Delete task',
    'delete_task_confirm' => 'Delete ":title"? Its history entries are kept.',
    'delete_entry_confirm' => 'Delete this history entry?',

    'due' => [
        'today' => 'Due today',
        'in_days' => 'Due in :count day|Due in :count days',
        'overdue' => ':count day overdue|:count days overdue',
        'none' => 'No due date',
    ],

    'dialog' => [
        'create_title' => 'Add maintenance task',
        'edit_title' => 'Edit maintenance task',
        'description' => 'Schedule upkeep for :name.',
        'title_label' => 'Title',
        'title_placeholder' => 'e.g. Change batteries (2× AA)',
        'notes_label' => 'Notes',
        'schedule_type_label' => 'Schedule',
        'type_hint' => [
            'interval' => 'The next due date counts from the day you actually do it.',
            'calendar' => 'Fixed dates on the calendar, no matter when it was last done.',
            'one_off' => 'A single due date; the task archives itself when done.',
        ],
        'interval_label' => 'Repeat every',
        'preset_label' => 'Rule',
        'preset_every' => 'Regular interval',
        'preset_yearly_on' => 'Yearly on a date',
        'preset_nth_weekday' => 'Weekday of the month',
        'month_label' => 'Month',
        'day_label' => 'Day',
        'ordinal_label' => 'Which one',
        'weekday_label' => 'Weekday',
        'every_month_option' => 'Every month',
        'due_date_label' => 'Due on',
        'lead_label' => 'Remind days before due',
        'custom_rule_note' => 'This task uses a custom rule (:summary); saving keeps it unchanged.',
        'submit_create' => 'Add task',
        'submit_save' => 'Save changes',
    ],

    'done_dialog' => [
        'title' => 'Mark ":title" done',
        'description' => 'Records a completion and moves the next due date forward.',
        'date_label' => 'Done on',
        'notes_label' => 'Notes',
        'cost_label' => 'Cost (:code)',
        'submit' => 'Mark done',
    ],

    'entry_dialog' => [
        'title' => 'Log maintenance',
        'description' => 'Record a repair or one-time maintenance for :name.',
        'date_label' => 'Done on',
        'notes_label' => 'What was done?',
        'cost_label' => 'Cost (:code)',
        'submit' => 'Log entry',
    ],

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
