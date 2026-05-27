<?php

declare(strict_types=1);

namespace App\Ai\Tools\Concerns;

use App\Models\Item;

/**
 * Builds the Markdown link the assistant should reuse when referring to an item,
 * so the model copies a correct link rather than composing a URL from an id it
 * might misremember. AssistantController validates these links before display.
 */
trait FormatsItemLinks
{
    protected function itemLink(Item $item): string
    {
        // Escape brackets so a name like "Box [A]" can't break the link label.
        $label = str_replace(['[', ']'], ['\[', '\]'], $item->name);

        return "[{$label}](/items/{$item->id})";
    }
}
