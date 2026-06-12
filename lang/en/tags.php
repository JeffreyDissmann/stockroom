<?php

declare(strict_types=1);

return [
    'title' => 'Tags',
    'subtitle' => 'Free-form labels you can attach to any item.',
    'new_tag' => 'New tag',
    'name_placeholder' => 'e.g. tools',
    'color' => 'Color',
    'add' => 'Add tag',
    'empty' => 'No tags yet.',
    'items_count' => ':count item|:count items',
    'show_tagged' => 'Show items tagged “:name”',
    'delete_confirm' => 'Delete tag ":name"? It will be removed from :count item(s).',
    'protected_hint' => 'Auto-assigned by the system — can\'t be deleted.',
    'cannot_delete_box_tag' => 'This tag is currently configured as the Box tag in household preferences.',
    'cannot_delete_box_tag_cta' => 'Change the preference first.',
    'cannot_delete_home_assistant_tag' => 'This tag is auto-assigned to items linked to Home Assistant and cannot be deleted.',
    'cannot_delete_battery_tag' => 'This tag is auto-assigned to battery-tracked items and cannot be deleted.',

    'filter' => [
        'all' => 'All tags',
        'count' => ':count tag|:count tags',
        'search' => 'Filter tags…',
        'none' => 'No tags found.',
        'clear' => 'Clear :count selected',
    ],
];
