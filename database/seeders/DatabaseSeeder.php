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
        $garage->tags()->sync(collect([$tools])->filter()->pluck('id')->all());
        $this->attachDemoImage($garage, 'garage');
        $this->makeItem($garage, ItemType::Container, 'Toolbox', 'Red metal toolbox on the workbench — sockets, screwdrivers, bits.', [$tools], 'toolbox', [
            'manufacturer' => 'DeWalt',
            'model_number' => 'DWST24070',
            'purchased_from' => 'Home Depot',
            'purchase_date' => '2023-04-12',
            'purchase_price' => '79.99',
        ]);
        $this->makeItem($garage, ItemType::Item, 'Lawnmower', 'Petrol push mower. Service the air filter every spring.', [$tools], 'lawnmower', [
            'manufacturer' => 'Honda',
            'model_number' => 'HRX217VKA',
            'serial_number' => 'MAGA-1903827',
            'purchased_from' => 'Acme Garden Centre',
            'purchase_date' => '2022-05-03',
            'purchase_price' => '649.00',
            'warranty_expires' => '2027-05-03',
            'warranty_details' => '5-year residential warranty.',
        ]);
        $this->makeItem($garage, ItemType::Item, 'Bicycle', 'Hybrid commuter. Hung on the wall hook by the side door.', [], 'bicycle', [
            'manufacturer' => 'Trek',
            'model_number' => 'FX 2 Disc',
            'serial_number' => 'WTU183K0512',
            'purchased_from' => 'Local Bike Shop',
            'purchase_date' => '2024-03-20',
            'purchase_price' => '699.99',
        ]);

        $kitchenRoom = Item::create([
            'type' => ItemType::Room,
            'name' => 'Kitchen',
            'description' => 'Open-plan kitchen with the island. Small appliances live on the counter, larger ones in the pantry.',
        ]);
        $kitchenRoom->tags()->sync(collect([$kitchen])->filter()->pluck('id')->all());
        $this->attachDemoImage($kitchenRoom, 'kitchen');
        $this->makeItem($kitchenRoom, ItemType::Item, 'Coffee maker', 'Daily-driver espresso machine. Descale monthly.', [$kitchen, $electronics], 'coffee-maker', [
            'manufacturer' => 'Breville',
            'model_number' => 'BES870XL',
            'serial_number' => 'BRV-7781204',
            'purchased_from' => 'Williams Sonoma',
            'purchase_date' => '2024-11-28',
            'purchase_price' => '699.95',
            'warranty_expires' => '2026-11-28',
            'warranty_details' => '2-year limited product warranty.',
        ]);
        $this->makeItem($kitchenRoom, ItemType::Item, 'Blender', 'High-power blender for smoothies and soups. The tamper lives in the cutlery drawer.', [$kitchen, $electronics], 'blender', [
            'manufacturer' => 'Vitamix',
            'model_number' => '5200',
            'purchased_from' => 'Costco',
            'purchase_date' => '2021-09-10',
            'purchase_price' => '449.99',
            'lifetime_warranty' => false,
            'warranty_expires' => '2028-09-10',
            'warranty_details' => '7-year full warranty.',
        ]);

        $office = Item::create([
            'type' => ItemType::Room,
            'name' => 'Office',
            'description' => 'Spare room turned home office. Desk by the window, bookcase opposite.',
        ]);
        $office->tags()->sync(collect([$electronics])->filter()->pluck('id')->all());
        $this->attachDemoImage($office, 'office');
        $this->makeItem($office, ItemType::Item, 'Laptop', '14-inch work laptop. Charger lives in the desk drawer.', [$electronics], 'laptop', [
            'manufacturer' => 'Apple',
            'model_number' => 'MacBook Pro 14" M3',
            'serial_number' => 'C02-FK9921Q6LVCG',
            'purchased_from' => 'Apple Store',
            'purchase_date' => '2024-02-14',
            'purchase_price' => '1999.00',
            'warranty_expires' => '2027-02-14',
            'warranty_details' => 'AppleCare+ through Feb 2027.',
        ]);

        // A retired item to demo the "Sold" section.
        $this->makeItem($office, ItemType::Item, 'Old monitor', 'Previous 27-inch display, replaced and sold on.', [$electronics], null, [
            'manufacturer' => 'Dell',
            'model_number' => 'U2719D',
            'purchase_date' => '2019-06-01',
            'purchase_price' => '329.00',
            'sold_to' => 'A colleague',
            'sold_price' => '120.00',
            'sold_date' => '2025-02-10',
            'sold_notes' => 'Sold when upgrading to the dual-4K setup.',
        ]);
    }

    /**
     * @param  array<int, Tag|null>  $tags
     * @param  array<string, mixed>  $details  Extra item columns (purchase/warranty/etc.)
     */
    private function makeItem(Item $parent, ItemType $type, string $name, ?string $description = null, array $tags = [], ?string $imageSlug = null, array $details = []): Item
    {
        $item = Item::create(array_merge([
            'parent_id' => $parent->id,
            'type' => $type,
            'name' => $name,
            'description' => $description,
        ], $details));

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
