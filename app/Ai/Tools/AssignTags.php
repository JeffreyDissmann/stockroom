<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\FormatsItemLinks;
use App\Models\Item;
use App\Models\Tag;
use App\Services\Items\ItemWriter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class AssignTags implements Tool
{
    use FormatsItemLinks;

    public function __construct(private readonly ItemWriter $writer) {}

    public function description(): string
    {
        return 'Attach one or more existing tags to an item (does not remove existing tags). '
            .'Only existing tags can be used — creating new tags is restricted to admins. Confirm with the user first.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('Id of the item to tag.')->required(),
            'tags' => $schema->string()->description('Comma-separated existing tag names, e.g. "Tools, Electronics".')->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $item = Item::find((int) ($request['id'] ?? 0));

        if (! $item) {
            return 'No item found with that id.';
        }

        $names = collect(explode(',', (string) ($request['tags'] ?? '')))
            ->map(fn (string $n): string => trim($n))
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            return 'No tag names were provided.';
        }

        $tags = Tag::whereIn('name', $names->all())->get();
        $unknown = $names->reject(fn (string $n): bool => $tags->contains(fn (Tag $t): bool => strcasecmp($t->name, $n) === 0));

        if ($tags->isNotEmpty()) {
            $this->writer->assignTags($item, $tags->pluck('id')->all());
        }

        $applied = $tags->isNotEmpty() ? 'Tagged '.$this->itemLink($item).' with: '.$tags->pluck('name')->implode(', ').'.' : 'No matching existing tags.';
        $skipped = $unknown->isNotEmpty() ? ' Unknown (not created): '.$unknown->implode(', ').'.' : '';

        return $applied.$skipped;
    }
}
