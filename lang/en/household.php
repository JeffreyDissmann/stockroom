<?php

declare(strict_types=1);

return [
    'title' => 'Household',
    'subtitle' => 'Settings shared across the whole home inventory',

    'nav' => [
        'custom_fields' => 'Custom fields',
        'backup' => 'Backup & import',
        'search_index' => 'Search index',
        'members' => 'Members',
        'preferences' => 'Preferences',
    ],

    'preferences' => [
        'description' => 'Household-wide settings that affect how Stockroom behaves.',
        'box_tag' => 'Box tag',
        'box_tag_none' => '(none — don\'t auto-tag boxes)',
        'box_tag_help' => 'When you create a packaging record via "Create a box for this item", this tag is attached automatically. Lets you filter the household\'s kept packaging in one place.',
    ],

    'import' => [
        'title' => 'Import from Homebox',
        'description' => 'Pull locations, items, photos, tags and custom fields from a running Homebox instance. Re-running updates existing items instead of duplicating them.',
        'url' => 'Homebox URL',
        'email' => 'Email',
        'password' => 'Password',
        'note' => 'Your credentials are used once to obtain a token and are never stored. The import runs in the background — a queue worker must be running.',
        'submit' => 'Connect & import',
        'progress' => 'Importing… :done / :total',
        'done' => 'Imported :entities entities (:created new, :updated updated) and :images photos',
        'skipped' => ' (:count unsupported photo(s) skipped)',
        'failed' => 'Import failed: :error',
    ],

    'search_index' => [
        'description' => 'Rebuild the full-text search index for all items. Useful after a bulk change, or to (re)generate semantic-search embeddings.',
        'count' => ':count item to index.|:count items to index.',
        'semantic_on' => 'Semantic search is on — unchanged items reuse cached embeddings, so re-runs are fast.',
        'semantic_off' => 'Semantic search is off (keyword only).',
        'worker_note' => 'The rebuild runs in the background — a queue worker must be running.',
        'rebuild' => 'Rebuild search index',
        'progress' => 'Indexing… :done / :total',
        'done' => 'Done — indexed :count item.|Done — indexed :count items.',
        'failed' => 'Reindex failed: :error',
    ],

    'backup' => [
        'description' => 'Download your entire inventory — items, tags and original photos — as a single .zip archive, or restore one. Derived image sizes are rebuilt automatically on restore.',
        'download' => 'Download backup',
        'restore_note' => 'Restoring updates items, tags and images with matching ids and adds anything new. Other items are left untouched.',
        'restore' => 'Restore backup',
        'result' => 'Restored :items item(s), :tags tag(s) and :images image(s).',
    ],

    'danger' => [
        'title' => 'Danger zone',
        'description' => 'Permanently delete the inventory — every item and photo. Export a backup first; this cannot be undone.',
        'include_tags' => 'Also delete all tags',
        'include_custom_fields' => 'Also delete all custom fields',
        'include_activity' => 'Also clear the activity log',
        'wipe' => 'Wipe inventory',
        'done' => 'Inventory wiped.',
        'confirm' => 'This permanently deletes every item and photo:tail. This cannot be undone. Continue?',
        'extra_tags' => 'all tags',
        'extra_custom_fields' => 'all custom fields',
        'extra_activity' => 'the activity log',
        'and' => 'and',
    ],

    'custom_fields' => [
        'description' => 'Define extra typed fields (e.g. Color, Voltage, Purchase URL) that can be filled in on any item.',
        'name_placeholder' => 'Field name',
        'searchable' => 'Searchable',
        'searchable_title' => "Include this field's values in search",
        'add' => 'Add field',
        'empty' => 'No custom fields yet.',
        'not_searchable' => 'Not searchable',
        'included' => 'Included in search',
        'excluded' => 'Excluded from search',
        'system' => 'System',
        'delete_confirm' => 'Delete the ":name" field? Its values on every item will be removed.',
    ],
];
