<?php

declare(strict_types=1);

namespace App\Services\Homebox;

use App\Enums\CustomFieldType;
use App\Enums\ItemType;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Item;
use App\Models\Tag;
use App\Services\ItemImageProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

/**
 * Imports a Homebox instance (v0.25+ entities API) into Stockroom. Locations
 * become Room/Container items, items become Item, labels become tags and Homebox
 * custom fields map onto ours. Each entity's Homebox UUID is stored in a system
 * `homebox_id` custom field so re-running updates instead of duplicating.
 */
class HomeboxImporter
{
    private CustomField $homeboxField;

    public function __construct(
        private readonly HomeboxClient $client,
        private readonly ItemImageProcessor $images,
    ) {}

    private int $imagesSkipped = 0;

    /**
     * @param  callable(int $done, int $total): void|null  $onProgress
     * @return array{entities: int, images: int, imagesSkipped: int, created: int, updated: int}
     */
    public function import(?int $maxEntities = null, ?callable $onProgress = null): array
    {
        $this->imagesSkipped = 0;
        $this->homeboxField = CustomField::firstOrCreate(
            ['key' => 'homebox_id'],
            ['name' => 'Homebox ID', 'type' => CustomFieldType::Text, 'is_system' => true],
        );

        // Locations live in the tree, items in the entities list. Item parents
        // reference either, so import both before wiring up the hierarchy.
        $locations = $this->flattenTree($this->client->tree());
        $summaries = $this->client->allEntities();
        if ($maxEntities !== null) {
            $summaries = array_slice($summaries, 0, $maxEntities);
        }

        $total = count($locations) + count($summaries);
        $map = [];          // homebox uuid => stockroom item id
        $created = $updated = $imageCount = $done = 0;

        // Pass 1a: locations -> Room (top level) / Container (nested).
        foreach ($locations as $location) {
            [$item, $wasCreated] = $this->upsertLocation($location);
            $map[$location['id']] = $item->id;
            $created += $wasCreated ? 1 : 0;
            $updated += $wasCreated ? 0 : 1;
            $done++;
            $onProgress && $onProgress($done, $total);
        }

        // Pass 1b: items, with their tags, custom fields and images.
        foreach (array_values($summaries) as $summary) {
            $detail = $this->client->entity($summary['id']);
            [$item, $wasCreated, $images] = $this->upsertEntity($detail);

            $map[$summary['id']] = $item->id;
            $created += $wasCreated ? 1 : 0;
            $updated += $wasCreated ? 0 : 1;
            $imageCount += $images;
            $done++;
            $onProgress && $onProgress($done, $total);
        }

        // Pass 2: wire up parents now that every entity exists. A location's
        // parent comes from the tree; an item's from its detail payload.
        foreach ($locations as $location) {
            $this->linkParent($map, $location['id'], $location['parentId']);
        }
        foreach ($summaries as $summary) {
            $this->linkParent($map, $summary['id'], Arr::get($summary, 'parent.id'));
        }

        // Re-index with the now-complete tags/custom fields and parent paths.
        Item::query()->whereKey(array_values($map))->get()->searchable();

        return [
            'entities' => $total,
            'images' => $imageCount,
            'imagesSkipped' => $this->imagesSkipped,
            'created' => $created,
            'updated' => $updated,
        ];
    }

    /**
     * Flatten the location tree into a list carrying each node's parent id.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @param  array<int, array{id: string, name: string, parentId: ?string}>  $flat
     * @return array<int, array{id: string, name: string, parentId: ?string}>
     */
    private function flattenTree(array $nodes, ?string $parentId = null, array &$flat = []): array
    {
        foreach ($nodes as $node) {
            if (($node['type'] ?? 'location') !== 'location') {
                continue; // items appear in the entities list, not here
            }

            $flat[] = ['id' => $node['id'], 'name' => (string) ($node['name'] ?? 'Location'), 'parentId' => $parentId];
            $this->flattenTree($node['children'] ?? [], $node['id'], $flat);
        }

        return $flat;
    }

    /**
     * @param  array{id: string, name: string, parentId: ?string}  $location
     * @return array{0: Item, 1: bool}
     */
    private function upsertLocation(array $location): array
    {
        $existing = $this->findByHomeboxId($location['id']);
        $item = $existing ?? new Item;

        // Homebox has no Room/Container distinction, so every location becomes a
        // Room; Containers only arise from items nested inside other items.
        $item->fill([
            'type' => ItemType::Room->value,
            'name' => trim($location['name']) ?: 'Location',
            'quantity' => 1,
        ]);
        $item->parent_id = null;
        $item->save();

        $this->setHomeboxId($item, $location['id']);

        return [$item, ! $existing];
    }

    /**
     * @param  array<string, int>  $map  homebox uuid => stockroom item id
     */
    private function linkParent(array $map, string $hbId, ?string $parentHbId): void
    {
        if ($parentHbId !== null && isset($map[$parentHbId], $map[$hbId])) {
            Item::query()->whereKey($map[$hbId])->update(['parent_id' => $map[$parentHbId]]);
        }
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array{0: Item, 1: bool, 2: int} [item, wasCreated, imagesImported]
     */
    private function upsertEntity(array $detail): array
    {
        $hbId = $detail['id'];
        $isLocation = (bool) Arr::get($detail, 'entityType.isLocation', false);
        $hasParent = Arr::get($detail, 'parent.id') !== null;

        $type = $isLocation
            ? ($hasParent ? ItemType::Container : ItemType::Room)
            : ItemType::Item;

        $existing = $this->findByHomeboxId($hbId);
        $item = $existing ?? new Item;

        $item->fill($this->attributes($detail, $type, $isLocation));
        $item->parent_id = null;
        $item->save();

        $this->setHomeboxId($item, $hbId);
        $this->syncTags($item, $detail['tags'] ?? []);
        $this->syncCustomFields($item, $detail['fields'] ?? []);
        $imageCount = $this->syncImages($item, $hbId, $detail['attachments'] ?? [], (bool) $existing);

        return [$item, ! $existing, $imageCount];
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    private function attributes(array $detail, ItemType $type, bool $isLocation): array
    {
        $attributes = [
            'type' => $type->value,
            'name' => trim((string) ($detail['name'] ?? '')) ?: 'Untitled',
            'description' => $this->description($detail),
        ];

        if ($isLocation) {
            return $attributes + ['quantity' => 1];
        }

        return $attributes + [
            'quantity' => (int) ($detail['quantity'] ?? 1),
            'manufacturer' => $this->text($detail['manufacturer'] ?? null),
            'model_number' => $this->text($detail['modelNumber'] ?? null),
            'serial_number' => $this->text($detail['serialNumber'] ?? null),
            'purchased_from' => $this->text($detail['purchaseFrom'] ?? null),
            'purchase_date' => $this->date($detail['purchaseDate'] ?? null),
            'purchase_price' => $this->price($detail['purchasePrice'] ?? null),
            'lifetime_warranty' => (bool) ($detail['lifetimeWarranty'] ?? false),
            'warranty_expires' => $this->date($detail['warrantyExpires'] ?? null),
            'warranty_details' => $this->text($detail['warrantyDetails'] ?? null),
            'sold_to' => $this->text($detail['soldTo'] ?? null),
            'sold_price' => $this->price($detail['soldPrice'] ?? null),
            'sold_date' => $this->date($detail['soldDate'] ?? null),
            'sold_notes' => $this->text($detail['soldNotes'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function description(array $detail): ?string
    {
        $description = trim((string) ($detail['description'] ?? ''));
        $notes = trim((string) ($detail['notes'] ?? ''));

        return match (true) {
            $description !== '' && $notes !== '' => "{$description}\n\n{$notes}",
            $notes !== '' => $notes,
            default => $description ?: null,
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $tags
     */
    private function syncTags(Item $item, array $tags): void
    {
        $ids = [];
        foreach ($tags as $tag) {
            $name = trim((string) ($tag['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $ids[] = Tag::firstOrCreate(['name' => $name], ['color' => $tag['color'] ?? null])->id;
        }

        $item->tags()->sync($ids);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     */
    private function syncCustomFields(Item $item, array $fields): void
    {
        foreach ($fields as $field) {
            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $type = match ($field['type'] ?? 'text') {
                'number' => CustomFieldType::Number,
                'boolean' => CustomFieldType::Boolean,
                default => CustomFieldType::Text,
            };

            $value = match ($type) {
                CustomFieldType::Number => $this->price($field['numberValue'] ?? null),
                CustomFieldType::Boolean => ! empty($field['booleanValue']) ? '1' : '0',
                default => $this->text($field['textValue'] ?? null),
            };

            if ($value === null) {
                continue;
            }

            $definition = CustomField::firstOrCreate(['name' => $name], ['type' => $type]);
            $item->customFieldValues()->updateOrCreate(
                ['custom_field_id' => $definition->id],
                ['value' => $value],
            );
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $attachments
     */
    private function syncImages(Item $item, string $hbId, array $attachments, bool $replace): int
    {
        if ($replace) {
            foreach ($item->images()->get() as $existing) {
                $existing->delete();
            }
        }

        // Homebox sometimes tags documents (e.g. PDF receipts) as "photo"; those
        // aren't item images. Everything else is attempted (some real photos
        // carry a generic application/octet-stream type) and skipped on failure.
        $photos = array_values(array_filter($attachments, function (array $a): bool {
            if (($a['type'] ?? '') !== 'photo') {
                return false;
            }

            $mime = strtolower((string) ($a['mimeType'] ?? ''));
            $title = strtolower((string) ($a['title'] ?? ''));

            return $mime !== 'application/pdf' && ! str_ends_with($title, '.pdf');
        }));
        $primary = null;
        $count = 0;

        foreach ($photos as $photo) {
            $temp = (string) tempnam(sys_get_temp_dir(), 'hb-img-');

            try {
                file_put_contents($temp, $this->client->downloadAttachment($hbId, $photo['id']));

                $name = (string) ($photo['title'] ?? 'image.jpg');
                $record = $this->images->store($item, new UploadedFile($temp, $name, $photo['mimeType'] ?? null, null, true));

                if (! empty($photo['primary'])) {
                    $primary = $record;
                }
                $count++;
            } catch (Throwable $e) {
                // One unreadable photo (e.g. HEIC, which GD can't decode) or a
                // failed download must not abort the whole import — skip it.
                report($e);
                $this->imagesSkipped++;
            } finally {
                @unlink($temp);
            }
        }

        // store() makes the first image primary; honour Homebox's choice instead.
        if ($primary !== null && ! $primary->is_primary) {
            $primary->update(['is_primary' => true]);
        }

        return $count;
    }

    private function findByHomeboxId(string $hbId): ?Item
    {
        $value = CustomFieldValue::query()
            ->where('custom_field_id', $this->homeboxField->id)
            ->where('value', $hbId)
            ->first();

        return $value !== null ? Item::find($value->item_id) : null;
    }

    private function setHomeboxId(Item $item, string $hbId): void
    {
        $item->customFieldValues()->updateOrCreate(
            ['custom_field_id' => $this->homeboxField->id],
            ['value' => $hbId],
        );
    }

    private function text(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * Homebox uses year-0001 dates as "empty".
     */
    private function date(mixed $value): ?string
    {
        $value = (string) ($value ?? '');
        if ($value === '' || Str::startsWith($value, '0001')) {
            return null;
        }

        return Str::substr($value, 0, 10);
    }

    private function price(mixed $value): ?string
    {
        if ($value === null || $value === '' || (float) $value === 0.0) {
            return null;
        }

        return (string) (float) $value;
    }
}
