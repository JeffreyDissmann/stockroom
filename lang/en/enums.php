<?php

declare(strict_types=1);

return [
    'item_type' => [
        'room' => 'Room',
        'container' => 'Container',
        'item' => 'Item',
    ],
    'custom_field_type' => [
        'text' => 'Text',
        'number' => 'Number',
        'date' => 'Date',
        'boolean' => 'Yes / No',
        'url' => 'Link',
    ],
    'maintenance_schedule_type' => [
        'interval' => 'After completion',
        'calendar' => 'Fixed schedule',
        'one_off' => 'Once',
        'forecast' => 'Battery forecast',
    ],
    'maintenance_interval_unit' => [
        'days' => 'Days',
        'weeks' => 'Weeks',
        'months' => 'Months',
        'years' => 'Years',
    ],

    'proposal_status' => [
        'pending' => 'Awaiting review',
        'accepted' => 'Accepted',
        'rejected' => 'Rejected',
    ],

    'proposal_field' => [
        'description' => 'Description',
        'manufacturer' => 'Manufacturer',
        'model_number' => 'Model number',
        'serial_number' => 'Serial number',
    ],
];
