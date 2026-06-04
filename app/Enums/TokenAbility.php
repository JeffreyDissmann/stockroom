<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The abilities a personal access token can hold. `read` covers every GET on
 * the v1 API; `write` is additionally required for mutations (guarded by the
 * `abilities:write` route middleware). Single source of truth for the valid
 * ability set — used to validate token creation.
 */
enum TokenAbility: string
{
    case Read = 'read';
    case Write = 'write';
}
