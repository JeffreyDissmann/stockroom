<?php

declare(strict_types=1);

namespace App\Ai\Concerns;

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
        return "[{$this->itemLinkLabel($item->name)}](/items/{$item->id})";
    }

    /**
     * Escape brackets in the link label so a name like "Box [A]" can't break it.
     */
    protected function itemLinkLabel(string $name): string
    {
        return str_replace(['[', ']'], ['\[', '\]'], $name);
    }
}
