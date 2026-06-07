<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Concerns\FormatsItemLinks;
use App\Models\CustomFieldValue;
use App\Models\Item;
use App\Models\MaintenanceEntry;
use App\Models\MaintenanceTask;
use App\Models\PaperlessLink;
use App\Models\Tag;
use App\Services\Maintenance\MaintenancePresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetItem implements Tool
{
    use FormatsItemLinks;

    /**
     * History shown per item — enough to answer "when did I last …" without
     * flooding the context for a frequently-serviced item.
     */
    private const int HISTORY_LIMIT = 5;

    public function __construct(private readonly MaintenancePresenter $presenter) {}

    public function description(): string
    {
        return 'Get the full details of a single inventory item by its id: type, location, quantity, '
            .'manufacturer/model/serial, purchase and warranty info, tags, custom fields, related items, '
            .'linked Paperless documents and Home Assistant device, plus its maintenance schedules and '
            .'recent maintenance history.';
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()->description('The item id (as returned by search_items).')->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $item = Item::with([
            'tags',
            'customFieldValues.field',
            'relatedItems',
            'paperlessLinks',
            'homeAssistantLink',
            'maintenanceTasks',
            'maintenanceEntries' => fn ($query) => $query->with(['task', 'performer'])->limit(self::HISTORY_LIMIT),
        ])->find((int) ($request['id'] ?? 0));

        if (! $item) {
            return 'No item found with that id.';
        }

        $lines = [
            "#{$item->id} {$this->itemLink($item)} ({$item->type->value})",
            'Location: '.($item->locationPath() ?: 'top level'),
        ];

        if ($item->description) {
            $lines[] = "Description: {$item->description}";
        }

        $details = array_filter([
            'Quantity' => $item->quantity,
            'Manufacturer' => $item->manufacturer,
            'Model' => $item->model_number,
            'Serial' => $item->serial_number,
            'Purchased from' => $item->purchased_from,
            'Purchase date' => $item->purchase_date?->toDateString(),
            'Purchase price' => $item->purchase_price,
            'Warranty until' => $item->lifetime_warranty ? 'lifetime' : $item->warranty_expires?->toDateString(),
            'Sold to' => $item->sold_to,
            'Sold price' => $item->sold_price,
            'Sold date' => $item->sold_date?->toDateString(),
        ], fn ($v) => $v !== null && $v !== '');

        foreach ($details as $label => $value) {
            $lines[] = "{$label}: {$value}";
        }

        if ($item->tags->isNotEmpty()) {
            $lines[] = 'Tags: '.$item->tags->map(fn (Tag $t): string => $t->name)->implode(', ');
        }

        foreach ($item->customFieldValues as $value) {
            /** @var CustomFieldValue $value */
            if ($value->value !== null && $value->value !== '') {
                $lines[] = "{$value->field->name}: {$value->value}";
            }
        }

        return implode("\n", [...$lines, ...$this->connectionLines($item), ...$this->maintenanceLines($item)]);
    }

    /**
     * External and internal links: related items, Paperless documents and
     * the Home Assistant device — the assistant's view of the Connections
     * card. Empty when the item has none.
     *
     * @return list<string>
     */
    private function connectionLines(Item $item): array
    {
        $lines = [];

        if ($item->relatedItems->isNotEmpty()) {
            $lines[] = 'Related items: '.$item->relatedItems
                ->map(fn (Item $related): string => $this->itemLink($related))
                ->implode(', ');
        }

        if ($item->paperlessLinks->isNotEmpty()) {
            $lines[] = 'Paperless documents: '.$item->paperlessLinks
                ->map(fn (PaperlessLink $link): string => $this->paperlessLinkLabel($link))
                ->implode(', ');
        }

        if (($link = $item->homeAssistantLink) !== null) {
            $label = $link->friendly_name ?? $link->ha_entity_id ?? $link->ha_device_id;
            $lines[] = 'Home Assistant device: '.($link->url ? "[{$label}]({$link->url})" : (string) $label);
        }

        return $lines;
    }

    /**
     * A Markdown link into Paperless, or the bare document id when the
     * integration is disabled and no URL can be composed.
     */
    private function paperlessLinkLabel(PaperlessLink $link): string
    {
        // Prefer the cached title (with the type as a qualifier) so the model
        // sees "Rechnung: AEG receipt" instead of a bare id; fall back to the
        // id on rows the repair job hasn't filled in yet.
        $label = match (true) {
            $link->document_title !== null && $link->document_type !== null => "{$link->document_type}: {$link->document_title}",
            $link->document_title !== null => $link->document_title,
            default => "Document {$link->paperless_document_id}",
        };

        $url = $link->paperlessUrl();

        return $url !== null
            ? "[{$label}]({$url})"
            : "{$label} (#{$link->paperless_document_id})";
    }

    /**
     * Active maintenance schedules (with task ids for follow-up tool calls)
     * and the most recent history entries.
     *
     * @return list<string>
     */
    private function maintenanceLines(Item $item): array
    {
        $lines = [];
        $tasks = $item->maintenanceTasks->filter(fn (MaintenanceTask $task): bool => $task->is_active);

        if ($tasks->isNotEmpty()) {
            $lines[] = 'Maintenance schedules:';

            foreach ($tasks as $task) {
                $lines[] = "- Task #{$task->id} \"{$task->title}\": {$this->presenter->dueLabel($task)}"
                    ." ({$task->next_due_at?->toDateString()}); {$this->presenter->scheduleSummary($task)}";
            }
        }

        if ($item->maintenanceEntries->isNotEmpty()) {
            $lines[] = 'Recent maintenance history (newest first, up to '.self::HISTORY_LIMIT.'):';

            foreach ($item->maintenanceEntries as $entry) {
                $lines[] = $this->entryLine($entry);
            }
        }

        return $lines;
    }

    private function entryLine(MaintenanceEntry $entry): string
    {
        $details = array_filter([
            $entry->task?->title ?? 'ad-hoc',
            $entry->performer !== null ? "by {$entry->performer->name}" : null,
            $entry->cost !== null ? 'cost '.$entry->cost.' '.config('stockroom.currency.code', 'USD') : null,
            $entry->notes,
        ]);

        return "- {$entry->completed_at->toDateString()}: ".implode('; ', $details);
    }
}
