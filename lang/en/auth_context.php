<?php

declare(strict_types=1);

/*
| Login-page context panel — pitch + GitHub link + version chip. Lives
| in its own group rather than `auth.php` because that's a reserved
| Laravel translation namespace and shipping it to the JS layer would
| leak framework strings ("These credentials do not match…") onto the
| auth pages.
*/

return [
    'pitch' => 'Stockroom is a self-hosted home inventory — every item, container and room, searchable, with an optional local AI assistant.',
    'status' => 'Beta. Read the CHANGELOG before upgrading.',
    'built_by' => 'Made by Jeffrey Dissmann',
    'github' => 'GitHub — issues & questions',
    'license' => 'MIT licensed',
];
