<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Concerns\FormatsItemLinks;
use App\Models\Item;
use Illuminate\Support\Str;

/**
 * Turns an assistant's raw Markdown reply into the safe HTML the panel renders:
 *
 *   1. Repair malformed item links ("[/items/12]" → proper "[Name](/items/12)").
 *   2. Render Markdown with raw HTML stripped and unsafe link schemes blocked.
 *   3. Validate every /items/{id} link against real ids; rewrite a wrong id when
 *      the label uniquely names a real item, otherwise strip the link to text.
 *
 * Lives outside the controller because none of these are HTTP concerns — the
 * link rules in particular are content-shaping that other consumers (history
 * rehydration, future tools) want too.
 */
class ReplyPresenter
{
    use FormatsItemLinks;

    public function render(string $text): string
    {
        return $this->validateItemLinks(Str::markdown($this->normaliseItemLinks($text), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }

    /**
     * Repair a common model mistake — writing the URL inside the brackets with
     * no label, e.g. "[/items/557]" — into a proper [Name](/items/557) link so
     * it renders instead of showing as literal text.
     */
    private function normaliseItemLinks(string $text): string
    {
        return preg_replace_callback('#\[/items/(\d+)\](?!\()#', function (array $match): string {
            $item = Item::find((int) $match[1]);

            return $item === null ? $match[0] : $this->itemLink($item);
        }, $text) ?? $text;
    }

    /**
     * Fix up /items/{id} links the model wrote. A link to a real id is kept; a
     * link to a missing id is self-healed when its text uniquely names a real
     * item (the model likely got the number wrong), otherwise it degrades to
     * plain text — so a hallucinated id can never become a 404 link.
     */
    private function validateItemLinks(string $html): string
    {
        preg_match_all('#<a\b[^>]*\bhref="/items/(\d+)"[^>]*>(.*?)</a>#is', $html, $matches, PREG_SET_ORDER);

        if ($matches === []) {
            return $html;
        }

        $existing = Item::whereIn('id', array_unique(array_map(static fn (array $m): int => (int) $m[1], $matches)))
            ->pluck('id')
            ->flip();

        foreach ($matches as [$anchor, $id, $label]) {
            if ($existing->has((int) $id)) {
                continue;
            }

            $healedId = $this->itemIdForLabel($label);

            $html = str_replace(
                $anchor,
                $healedId !== null ? str_replace("/items/{$id}", "/items/{$healedId}", $anchor) : $label,
                $html,
            );
        }

        return $html;
    }

    /**
     * Resolve a link's visible text to a real item id, but only when exactly one
     * item bears that name (an ambiguous or unknown name can't be healed safely).
     */
    private function itemIdForLabel(string $label): ?int
    {
        $name = trim(html_entity_decode(strip_tags($label), ENT_QUOTES));

        if ($name === '') {
            return null;
        }

        // Case-insensitive exact match (no wildcards added); heal only when unambiguous.
        $ids = Item::whereLike('name', $name, caseSensitive: false)->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }
}
