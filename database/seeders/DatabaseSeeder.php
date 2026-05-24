<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use App\Services\ItemImageProcessor;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdmin();
        $this->seedTags();
        $this->seedDemoInventory();
    }

    private function seedAdmin(): void
    {
        User::updateOrCreate(
            ['email' => config('stockroom.admin.email')],
            [
                'name' => config('stockroom.admin.name'),
                'password' => Hash::make(config('stockroom.admin.password')),
                'email_verified_at' => now(),
            ],
        );
    }

    private function seedTags(): void
    {
        $tags = [
            ['name' => 'Tools', 'color' => '#f59e0b'],
            ['name' => 'Electronics', 'color' => '#3b82f6'],
            ['name' => 'Kitchen', 'color' => '#10b981'],
        ];

        foreach ($tags as $tag) {
            Tag::firstOrCreate(['name' => $tag['name']], ['color' => $tag['color']]);
        }
    }

    private function seedDemoInventory(): void
    {
        if (Item::query()->exists()) {
            return;
        }

        $tools = Tag::where('name', 'Tools')->first();
        $electronics = Tag::where('name', 'Electronics')->first();
        $kitchen = Tag::where('name', 'Kitchen')->first();

        $garage = Item::create([
            'type' => ItemType::Room,
            'name' => 'Garage',
            'description' => 'Detached one-car garage. Workbench under the window, shelving along the back wall.',
        ]);
        $this->attachDemoImage($garage, 'garage');
        $this->makeItem($garage, ItemType::Container, 'Toolbox', 'Red metal toolbox on the workbench — sockets, screwdrivers, bits.', [$tools], 'toolbox');
        $this->makeItem($garage, ItemType::Item, 'Lawnmower', 'Petrol push mower. Service the air filter every spring.', [$tools], 'lawnmower');
        $this->makeItem($garage, ItemType::Item, 'Bicycle', 'Hybrid commuter. Hung on the wall hook by the side door.', [], 'bicycle');

        $kitchenRoom = Item::create([
            'type' => ItemType::Room,
            'name' => 'Kitchen',
            'description' => 'Open-plan kitchen with the island. Small appliances live on the counter, larger ones in the pantry.',
        ]);
        $this->attachDemoImage($kitchenRoom, 'kitchen');
        $this->makeItem($kitchenRoom, ItemType::Item, 'Coffee maker', 'Daily-driver espresso machine. Descale monthly.', [$kitchen, $electronics], 'coffee-maker');
        $this->makeItem($kitchenRoom, ItemType::Item, 'Blender', 'High-power blender for smoothies and soups. The tamper lives in the cutlery drawer.', [$kitchen, $electronics], 'blender');

        $office = Item::create([
            'type' => ItemType::Room,
            'name' => 'Office',
            'description' => 'Spare room turned home office. Desk by the window, bookcase opposite.',
        ]);
        $this->attachDemoImage($office, 'office');
        $this->makeItem($office, ItemType::Item, 'Laptop', '14-inch work laptop. Charger lives in the desk drawer.', [$electronics], 'laptop');
    }

    /**
     * @param  array<int, Tag|null>  $tags
     */
    private function makeItem(Item $parent, ItemType $type, string $name, ?string $description = null, array $tags = [], ?string $imageSlug = null): Item
    {
        $item = Item::create([
            'parent_id' => $parent->id,
            'type' => $type,
            'name' => $name,
            'description' => $description,
        ]);

        $tagIds = collect($tags)->filter()->pluck('id')->all();
        if ($tagIds) {
            $item->tags()->sync($tagIds);
        }

        if ($imageSlug !== null) {
            $this->attachDemoImage($item, $imageSlug);
        }

        return $item;
    }

    /**
     * Attach the hand-picked sample image from database/seeders/sample-images/{slug}.jpg
     * to an item. The image is fed through the real ItemImageProcessor so it gets
     * the same resize/EXIF-strip treatment as a user upload.
     *
     * Bundled photos are documented in [sample-images/README.md] (Unsplash, free license).
     * Silently no-ops if the file is missing — seeding stays usable when a contributor
     * has cloned without LFS or pruned the assets.
     */
    private function attachDemoImage(Item $item, string $slug): void
    {
        $path = database_path("seeders/sample-images/{$slug}.jpg");
        if (! is_file($path)) {
            return;
        }

        $file = new UploadedFile(
            path: $path,
            originalName: "{$slug}.jpg",
            mimeType: 'image/jpeg',
            error: null,
            test: true,
        );

        app(ItemImageProcessor::class)->store($item, $file);
    }
}
