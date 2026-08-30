<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What became of a container's contents when it was sold.
 *
 * There is no default and no third option: the tools went with the toolbox, or
 * the toolbox went and the tools stayed. Guessing either way is wrong in a way
 * nobody notices for months, so the sale asks.
 */
enum SaleDisposition: string
{
    case SoldWithIt = 'sold';
    case Kept = 'kept';
}
