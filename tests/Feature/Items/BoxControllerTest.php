<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows any authenticated user to create a box (no admin gate)', function () {
    // Box creation is intentionally open to all members, matching item edit.
    $item = Item::factory()->create();

    $this->actingAs(User::factory()->create()) // non-admin
        ->post("/items/{$item->id}/box")
        ->assertRedirect();

    expect(Item::query()->where('parent_id', $item->id)->count())->toBe(1);
});

it('redirects guests to login', function () {
    $item = Item::factory()->create();

    $this->post("/items/{$item->id}/box")->assertRedirect('/login');
});

it('creates a child container with prefixed name and copied metadata', function () {
    $admin = User::factory()->admin()->create();
    $room = Item::factory()->room()->create(['name' => 'Office']);
    $phone = Item::factory()->create([
        'type' => ItemType::Item,
        'name' => 'iPhone 15 Pro',
        'parent_id' => $room->id,
        'serial_number' => 'SN-12345',
        'manufacturer' => 'Apple',
        'description' => 'Titanium, 256 GB.',
        'quantity' => 1,
    ]);

    $this->actingAs($admin)
        ->post("/items/{$phone->id}/box")
        ->assertRedirect();

    $box = Item::query()->where('parent_id', $phone->id)->firstOrFail();

    expect($box->name)->toBe('BOX: iPhone 15 Pro')
        ->and($box->type)->toBe(ItemType::Container)
        ->and($box->parent_id)->toBe($phone->id)
        ->and($box->serial_number)->toBe('SN-12345')
        ->and($box->manufacturer)->toBe('Apple')
        ->and($box->description)->toBe('Titanium, 256 GB.')
        ->and($box->quantity)->toBe(1);
});

it('attaches the configured box tag from settings', function () {
    $admin = User::factory()->admin()->create();
    $item = Item::factory()->create(['name' => 'Drill']);

    $this->actingAs($admin)
        ->post("/items/{$item->id}/box")
        ->assertRedirect();

    $box = Item::query()->where('parent_id', $item->id)->firstOrFail();
    $boxTag = Tag::query()->where('name', 'Box')->firstOrFail();

    expect($box->tags->pluck('id')->all())->toContain($boxTag->id);
});

it('skips tagging when the admin has cleared the box tag setting', function () {
    Setting::set('box_tag_id', null);

    $admin = User::factory()->admin()->create();
    $item = Item::factory()->create(['name' => 'Drill']);

    $this->actingAs($admin)
        ->post("/items/{$item->id}/box")
        ->assertRedirect();

    $box = Item::query()->where('parent_id', $item->id)->firstOrFail();

    expect($box->tags)->toBeEmpty();
});

it('honours request overrides for name and other fields', function () {
    $admin = User::factory()->admin()->create();
    $item = Item::factory()->create(['name' => 'Drill', 'manufacturer' => 'DeWalt']);

    $this->actingAs($admin)
        ->post("/items/{$item->id}/box", [
            'name' => 'DeWalt Drill OG box',
            'manufacturer' => 'DeWalt (rebranded)',
            'quantity' => 3,
        ])
        ->assertRedirect();

    $box = Item::query()->where('parent_id', $item->id)->firstOrFail();

    expect($box->name)->toBe('DeWalt Drill OG box')
        ->and($box->manufacturer)->toBe('DeWalt (rebranded)')
        ->and($box->quantity)->toBe(3);
});

it('allows multiple boxes per item (nested packaging)', function () {
    $admin = User::factory()->admin()->create();
    $item = Item::factory()->create(['name' => 'Camera']);

    $this->actingAs($admin)->post("/items/{$item->id}/box")->assertRedirect();
    $this->actingAs($admin)->post("/items/{$item->id}/box", ['name' => 'Camera inner box'])->assertRedirect();

    expect(Item::query()->where('parent_id', $item->id)->count())->toBe(2);
});

it('redirects to the newly created box show page with focus=images and a success flash', function () {
    $admin = User::factory()->admin()->create();
    $item = Item::factory()->create(['name' => 'Drill']);

    $response = $this->actingAs($admin)->post("/items/{$item->id}/box");
    $box = Item::query()->where('parent_id', $item->id)->firstOrFail();

    $response
        ->assertRedirect("/items/{$box->id}?focus=images")
        ->assertSessionHas('box_created_for', 'Drill');
});
