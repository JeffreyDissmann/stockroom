<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\AssignTags;
use App\Ai\Tools\CreateItem;
use App\Ai\Tools\DeleteItem;
use App\Ai\Tools\GetItem;
use App\Ai\Tools\InventoryStats;
use App\Ai\Tools\MoveItem;
use App\Ai\Tools\SearchItems;
use App\Ai\Tools\UpdateItem;
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
    use Promptable;
    use RemembersConversations;

    public function instructions(): string
    {
        $language = config('app.supported_locales.'.app()->getLocale().'.ai', 'English');

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

        Be concise and practical. Reply in {$language}.
        PROMPT;
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
