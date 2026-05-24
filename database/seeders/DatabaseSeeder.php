<?php

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
            ['name' => 'tools', 'color' => '#f59e0b'],
            ['name' => 'electronics', 'color' => '#3b82f6'],
            ['name' => 'kitchen', 'color' => '#10b981'],
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

        $tools = Tag::where('name', 'tools')->first();
        $electronics = Tag::where('name', 'electronics')->first();
        $kitchen = Tag::where('name', 'kitchen')->first();

        $garage = Item::create([
            'type' => ItemType::Room,
            'name' => 'Garage',
            'description' => 'Where the car lives.',
        ]);
        $this->makeItem($garage, ItemType::Container, 'Toolbox', tags: [$tools]);
        $this->makeItem($garage, ItemType::Item, 'Lawnmower', tags: [$tools]);
        $this->makeItem($garage, ItemType::Item, 'Bicycle');

        $kitchenRoom = Item::create([
            'type' => ItemType::Room,
            'name' => 'Kitchen',
            'description' => 'Cooking and coffee.',
        ]);
        $this->makeItem($kitchenRoom, ItemType::Item, 'Coffee maker', tags: [$kitchen, $electronics]);
        $this->makeItem($kitchenRoom, ItemType::Item, 'Blender', tags: [$kitchen, $electronics]);

        $office = Item::create([
            'type' => ItemType::Room,
            'name' => 'Office',
            'description' => 'Desk + chair + the laptop.',
        ]);
        $this->makeItem($office, ItemType::Item, 'Laptop', tags: [$electronics]);
    }

    /**
     * @param  array<int, \App\Models\Tag|null>  $tags
     */
    private function makeItem(Item $parent, ItemType $type, string $name, array $tags = []): Item
    {
        $item = Item::create([
            'parent_id' => $parent->id,
            'type' => $type,
            'name' => $name,
        ]);

        $tagIds = collect($tags)->filter()->pluck('id')->all();
        if ($tagIds) {
            $item->tags()->sync($tagIds);
        }

        return $item;
    }
}
