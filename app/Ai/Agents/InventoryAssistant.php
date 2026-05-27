<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\AssignTags;
use App\Ai\Tools\Concerns\FormatsItemLinks;
use App\Ai\Tools\CreateItem;
use App\Ai\Tools\DeleteItem;
use App\Ai\Tools\GetItem;
use App\Ai\Tools\InventoryStats;
use App\Ai\Tools\MoveItem;
use App\Ai\Tools\SearchItems;
use App\Ai\Tools\UpdateItem;
use App\Models\Item;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;

/**
 * Conversational assistant over the household inventory. Uses tools to read
 * (search/get/stats) and, after confirming with the user, write (create/update/
 * move/tag/delete) items. RemembersConversations persists multi-turn history;
 * every write goes through ItemWriter so it's validated, re-indexed and audited.
 */
class InventoryAssistant implements Agent, Conversational, HasTools
{
    use FormatsItemLinks;
    use Promptable;
    use RemembersConversations;

    /**
     * The item the user is currently viewing, if the chat was opened from an
     * item page. Ambient context only — never the forced subject of the chat.
     */
    private ?Item $currentItem = null;

    public function aboutItem(?Item $item): static
    {
        $this->currentItem = $item;

        return $this;
    }

    public function instructions(): string
    {
        $language = config('app.supported_locales.'.app()->getLocale().'.ai', 'English');
        $context = $this->currentItemContext();

        return <<<PROMPT
        You are the assistant for Stockroom, a shared home-inventory app. The inventory is a tree of
        rooms and containers (places) holding items (possessions); each item may have a location,
        quantity, purchase price, warranty, tags and custom fields.

        Use the tools to answer questions and make changes — never invent item ids or data:
        - To find or locate things, call search_items; for full details call get_item with an id.
        - For "how many" / "total value" questions, call inventory_stats. When the user means actual
          possessions (e.g. "how many items"), pass type="item" so rooms and containers aren't counted.
        - You may create, update, move, tag and delete items. **Always describe the exact change and
          get the user's explicit confirmation BEFORE calling any write tool** (create_item, update_item,
          move_item, assign_tags, delete_item). Deletion is permanent — be especially careful.
        - assign_tags can only attach tags that already exist; you cannot create tags.
        - When you mention a specific item, link it using the Markdown link the tools give you,
          written EXACTLY as [Name](/items/12) — never as [/items/12] or a bare URL. Reuse the exact
          link from the tool output; never invent ids.
        - To move or place something into a room/container, pass move_item a parent_name (the place's
          name) and let it resolve the id — do not guess an id, and do not move to the top level unless
          the user explicitly asks.

        Be concise and practical. Reply in {$language}. Format your answers in Markdown — use
        **bold** for key values, and bullet lists when presenting several items — so they render nicely.{$context}
        PROMPT;
    }

    /**
     * An ambient note about the item the user is viewing, so "this"/"it" resolve
     * to it — but the model should not raise it unprompted.
     */
    private function currentItemContext(): string
    {
        if ($this->currentItem === null) {
            return '';
        }

        $link = $this->itemLink($this->currentItem);

        return "\n\nContext: the user is currently viewing {$link} (a {$this->currentItem->type->value}). "
            .'If they say "this", "it" or "this item" without naming something else, they mean it. '
            .'Use this only when relevant — do not bring it up otherwise.';
    }

    /**
     * @return iterable<Tool>
     */
    public function tools(): iterable
    {
        return [
            app(SearchItems::class),
            app(GetItem::class),
            app(InventoryStats::class),
            app(CreateItem::class),
            app(UpdateItem::class),
            app(MoveItem::class),
            app(AssignTags::class),
            app(DeleteItem::class),
        ];
    }
}
