<?php

declare(strict_types=1);

return [
    'title' => 'Activity',
    'subtitle' => 'A log of changes made to items, tags and custom fields.',
    'empty' => 'No activity yet.',

    'verbs' => [
        'added' => 'Added',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'moved' => 'Moved',
    ],

    'words' => [
        'to' => 'to',
        'from' => 'from',
        'unknown' => 'unknown',
    ],

    'subjects' => [
        'item' => 'item',
        'tag' => 'tag',
        'custom_field' => 'custom field',
        'user' => 'user',
    ],

    // Friendly labels for changed fields shown in the diff rows.
    'fields' => [
        'name' => 'name',
        'description' => 'description',
        'type' => 'type',
        'icon' => 'icon',
        'quantity' => 'quantity',
        'manufacturer' => 'manufacturer',
        'model_number' => 'model number',
        'serial_number' => 'serial number',
        'purchased_from' => 'purchased from',
        'purchase_date' => 'purchase date',
        'purchase_price' => 'purchase price',
        'lifetime_warranty' => 'lifetime warranty',
        'warranty_expires' => 'warranty expiry',
        'warranty_details' => 'warranty details',
        'sold_to' => 'buyer',
        'sold_price' => 'sold price',
        'sold_date' => 'sold date',
        'sold_notes' => 'sold notes',
        'parent_name' => 'location',
        'is_searchable' => 'searchable',
        'is_admin' => 'admin',
        'color' => 'colour',
        'email' => 'email',
        'slug' => 'slug',
    ],

    'images_count' => ':count image|:count images',

    'time' => [
        'just_now' => 'just now',
        'minutes' => ':countm ago',
        'hours' => ':counth ago',
        'days' => ':countd ago',
    ],
];
