<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemDetailFieldsTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Espresso machine',
            'type' => 'item',
            'quantity' => 2,
            'purchased_from' => 'Beans & Co',
            'purchase_date' => '2025-01-15',
            'purchase_price' => '899.50',
            'manufacturer' => 'Breville',
            'model_number' => 'BES870',
            'serial_number' => 'SN-123456',
            'lifetime_warranty' => false,
            'warranty_expires' => '2027-01-15',
            'warranty_details' => '2-year manufacturer warranty.',
            'sold_to' => null,
            'sold_price' => null,
            'sold_date' => null,
            'sold_notes' => null,
        ], $overrides);
    }

    public function test_create_persists_all_detail_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/items', $this->payload());

        $item = Item::where('name', 'Espresso machine')->firstOrFail();
        $this->assertSame(2, $item->quantity);
        $this->assertSame('Beans & Co', $item->purchased_from);
        $this->assertSame('2025-01-15', $item->purchase_date->toDateString());
        $this->assertSame('899.50', $item->purchase_price);
        $this->assertSame('Breville', $item->manufacturer);
        $this->assertSame('BES870', $item->model_number);
        $this->assertSame('SN-123456', $item->serial_number);
        $this->assertFalse($item->lifetime_warranty);
        $this->assertSame('2027-01-15', $item->warranty_expires->toDateString());
        $this->assertSame('2-year manufacturer warranty.', $item->warranty_details);
    }

    public function test_quantity_defaults_to_one_when_omitted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/items', ['name' => 'Bare item', 'type' => 'item']);

        $this->assertSame(1, Item::where('name', 'Bare item')->firstOrFail()->quantity);
    }

    public function test_update_changes_detail_and_sale_fields(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create(['name' => 'Old']);

        $this->actingAs($user)->put("/items/{$item->id}", $this->payload([
            'name' => 'Old',
            'sold_to' => 'Jane Buyer',
            'sold_price' => '450.00',
            'sold_date' => '2026-03-01',
            'sold_notes' => 'Sold on marketplace.',
        ]));

        $item->refresh();
        $this->assertSame('Jane Buyer', $item->sold_to);
        $this->assertSame('450.00', $item->sold_price);
        $this->assertSame('2026-03-01', $item->sold_date->toDateString());
        $this->assertSame('Breville', $item->manufacturer);
    }

    public function test_lifetime_warranty_is_cast_to_boolean(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/items', $this->payload([
            'lifetime_warranty' => true,
            'warranty_expires' => null,
        ]));

        $this->assertTrue(Item::where('name', 'Espresso machine')->firstOrFail()->lifetime_warranty);
    }

    public function test_show_payload_exposes_detail_fields(): void
    {
        $user = User::factory()->create();
        $item = Item::factory()->create([
            'name' => 'Drill',
            'manufacturer' => 'DeWalt',
            'serial_number' => 'D-99',
            'purchase_price' => '129.99',
        ]);

        $this->actingAs($user)
            ->get("/items/{$item->id}")
            ->assertInertia(fn ($page) => $page
                ->component('items/Show')
                ->where('item.manufacturer', 'DeWalt')
                ->where('item.serial_number', 'D-99')
                ->where('item.purchase_price', '129.99')
            );
    }

    public function test_negative_price_and_bad_date_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/items/create')
            ->post('/items', $this->payload(['purchase_price' => '-5', 'purchase_date' => 'not-a-date']))
            ->assertSessionHasErrors(['purchase_price', 'purchase_date']);
    }

    public function test_quantity_cannot_be_negative(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/items/create')
            ->post('/items', $this->payload(['quantity' => -3]))
            ->assertSessionHasErrors('quantity');
    }

    public function test_rooms_strip_detail_fields_even_if_submitted(): void
    {
        $user = User::factory()->create();

        // A crafted request supplying detail fields for a room must not persist them.
        $this->actingAs($user)->post('/items', $this->payload([
            'name' => 'Garage',
            'type' => 'room',
            'quantity' => 9,
            'sold_to' => 'Someone',
            'sold_price' => '10.00',
        ]));

        $room = Item::where('name', 'Garage')->firstOrFail();
        $this->assertSame(1, $room->quantity);
        $this->assertNull($room->manufacturer);
        $this->assertNull($room->serial_number);
        $this->assertNull($room->purchase_price);
        $this->assertNull($room->warranty_expires);
        $this->assertFalse($room->lifetime_warranty);
        $this->assertNull($room->sold_to);
        $this->assertNull($room->sold_price);
    }

    public function test_type_descriptor_reports_detail_support(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/items/create')
            ->assertInertia(fn ($page) => $page
                ->component('items/Create')
                ->where('types', fn ($types) => collect($types)->firstWhere('value', 'room')['details'] === false
                    && collect($types)->firstWhere('value', 'item')['details'] === true)
            );
    }
}
