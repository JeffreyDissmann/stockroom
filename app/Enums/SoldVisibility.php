<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How much of the archive a search should see.
 *
 * Absent means the inventory alone, which is the default everywhere. `Only` is
 * a different question from the other two — what did I sell, and for how much —
 * so the archive is the whole answer rather than a fringe of it.
 */
enum SoldVisibility: string
{
    case Include = 'include';
    case Only = 'only';
}
