<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemImage;
use App\Models\ItemProposal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * A small, realistic household for working on the photo-review feature.
 *
 * Descriptions are deliberately uneven — some empty, some partial, some
 * complete — because a seed where everything is already described proves
 * nothing about a feature whose whole job is spotting what is missing.
 *
 * Photos are real ones already on this machine, referenced by their existing
 * directory id. Development only: it deletes every item first.
 */
class DevInventorySeeder extends Seeder
{
    /** @var array<int, array{room: string, items: array<int, array{name: string, imgs: array<int, string>, desc: string|null, mfr: string|null}>}> */
    private const PLAN = [
        ['room' => 'Wohnzimmer', 'items' => [
            ['name' => 'Sonos Beam', 'imgs' => ['1359'], 'desc' => 'Soundbar unter dem Fernseher.', 'mfr' => null],
            ['name' => 'Nintendo Switch', 'imgs' => ['1502'], 'desc' => null, 'mfr' => null],
            ['name' => 'Hue Zubehör', 'imgs' => ['1473', '1439', '1440'], 'desc' => null, 'mfr' => 'Philips'],
        ]],
        ['room' => 'Büro', 'items' => [
            ['name' => 'Fritz!Box', 'imgs' => ['1401'], 'desc' => 'Router im Regal. Anschluss Vodafone Kabel.', 'mfr' => null],
            ['name' => 'Externe SSD', 'imgs' => ['1565'], 'desc' => null, 'mfr' => null],
            ['name' => 'Türkamera', 'imgs' => ['1353'], 'desc' => 'Noch originalverpackt.', 'mfr' => 'Logitech'],
        ]],
        ['room' => 'Keller', 'items' => [
            ['name' => 'Kiste Lego', 'imgs' => ['1438'], 'desc' => 'Gekauft 2018 auf dem Flohmarkt.', 'mfr' => null],
            ['name' => 'Werkzeug', 'imgs' => ['45'], 'desc' => null, 'mfr' => null],
            ['name' => 'Lego Technic Set', 'imgs' => ['1370'], 'desc' => 'Geschenk, ungeöffnet.', 'mfr' => 'LEGO'],
        ]],
    ];

    public function run(): void
    {
        ItemProposal::query()->delete();
        Item::query()->delete();

        foreach (self::PLAN as $group) {
            $room = Item::create(['name' => $group['room'], 'type' => 'room']);

            foreach ($group['items'] as $spec) {
                $item = Item::create([
                    'name' => $spec['name'],
                    'parent_id' => $room->id,
                    'type' => 'item',
                    'description' => $spec['desc'],
                    'manufacturer' => $spec['mfr'],
                ]);

                foreach (array_values($spec['imgs']) as $position => $sourceId) {
                    $this->attachPhoto($item, $sourceId, $position);
                }
            }
        }

        $this->command?->info('Seeded '.Item::where('type', 'item')->count().' items across '.count(self::PLAN).' rooms.');
    }

    private function attachPhoto(Item $item, string $sourceId, int $position = 0): void
    {
        $source = storage_path("app/public/item-images/{$sourceId}");

        if (! File::isDirectory($source)) {
            $this->command?->warn("No photo {$sourceId} on disk; {$item->name} seeded without one.");

            return;
        }

        $image = ItemImage::create([
            'item_id' => $item->id,
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'width_original' => 1600,
            'height_original' => 1200,
            'size_bytes_original' => 500_000,
            'sort_order' => $position,
            'is_primary' => $position === 0,
        ]);

        $destination = storage_path("app/public/{$image->directory()}");
        File::ensureDirectoryExists($destination);

        foreach (['thumb.jpg', 'large.jpg', 'original.jpg'] as $file) {
            File::copy("{$source}/{$file}", "{$destination}/{$file}");
        }
    }
}
