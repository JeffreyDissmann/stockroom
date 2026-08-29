<?php

declare(strict_types=1);

return [
    'title' => 'Review suggestions',
    'description' => 'Stockroom looks at item photos overnight and suggests what their entries might be missing. Nothing is changed until you accept it.',
    'empty' => 'No suggestions waiting. Stockroom reviews photos it has not seen yet, nightly.',
    'current' => 'Now',
    'proposed' => 'Suggested',
    'empty_field' => '(empty)',
    'accept' => 'Accept',
    'reject' => 'Dismiss',
    'stale' => 'This item changed after the suggestion was made, so accepting it would undo that edit. Dismiss it and let tonight\'s review look again.',
    'stale_badge' => 'Item changed since',
    'already_reviewed' => 'Someone already decided on this suggestion.',
    'from_model' => 'Read from :count photo by :model|Read from :count photos by :model',
    'run' => 'Review photos now',
    'run_none' => 'Stockroom has looked at every photo.',
    'run_pending' => 'Stockroom has not looked at the photos of :count item yet.|Stockroom has not looked at the photos of :count items yet.',
    'running' => 'Looking at photos… :done / :total',
    'run_done' => 'Done — :total reviewed, :count new suggestion.|Done — :total reviewed, :count new suggestions.',
    'run_failed_some' => ':count item could not be read; it stays in the queue.|:count items could not be read; they stay in the queue.',
    'run_failed' => 'The review failed: :error',
];
