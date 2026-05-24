<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
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
        $this->makeItem($garage, ItemType::Container, 'Toolbox', 'Red metal toolbox on the workbench — sockets, screwdrivers, bits.', [$tools]);
        $this->makeItem($garage, ItemType::Item, 'Lawnmower', 'Petrol push mower. Service the air filter every spring.', [$tools]);
        $this->makeItem($garage, ItemType::Item, 'Bicycle', 'Hybrid commuter. Hung on the wall hook by the side door.');

        $kitchenRoom = Item::create([
            'type' => ItemType::Room,
            'name' => 'Kitchen',
            'description' => 'Open-plan kitchen with the island. Small appliances live on the counter, larger ones in the pantry.',
        ]);
        $this->makeItem($kitchenRoom, ItemType::Item, 'Coffee maker', 'Daily-driver espresso machine. Descale monthly.', [$kitchen, $electronics]);
        $this->makeItem($kitchenRoom, ItemType::Item, 'Blender', 'High-power blender for smoothies and soups. The tamper lives in the cutlery drawer.', [$kitchen, $electronics]);

        $office = Item::create([
            'type' => ItemType::Room,
            'name' => 'Office',
            'description' => 'Spare room turned home office. Desk by the window, bookcase opposite.',
        ]);
        $this->makeItem($office, ItemType::Item, 'Laptop', '14-inch work laptop. Charger lives in the desk drawer.', [$electronics]);
    }

    /**
     * @param  array<int, Tag|null>  $tags
     */
    private function makeItem(Item $parent, ItemType $type, string $name, ?string $description = null, array $tags = []): Item
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

        return $item;
    }
}
