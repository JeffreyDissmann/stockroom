<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\Tag;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists items and filters with search across grid and list views', function () {
    Item::factory()->room()->create(['name' => 'Garage']);
    Item::factory()->room()->create(['name' => 'Kitchen']);

    $page = visit('/items');

    $page->assertSee('Garage')
        ->assertSee('Kitchen')
        ->click('List')
        ->assertSee('Garage')
        ->type('input[type=search]', 'Gara')
        ->assertSee('Garage')
        ->assertDontSee('Kitchen')
        ->assertNoJavaScriptErrors();
});

it('drills into a room to see its contents', function () {
    $garage = Item::factory()->room()->create(['name' => 'Garage']);
    Item::factory()->container()->create(['name' => 'Toolbox', 'parent_id' => $garage->id]);

    $page = visit('/items');

    $page->click('Garage')
        ->assertPathIs("/items/{$garage->id}")
        ->assertSee('Garage')
        ->assertSee('Contents')
        ->assertSee('Toolbox')
        ->assertNoJavaScriptErrors();
});

it('queues a selected image in the create dropzone', function () {
    // The end-to-end upload+persist is covered by ItemImageTest (feature). Here we
    // assert the dropzone wiring in a real browser: a picked file produces a
    // "Queued" preview. NOTE: the Pest browser plugin's in-container test server
    // does not complete a multipart file submit (verified working via the live app
    // + host Playwright), so we don't submit the file through it here.
    $page = visit('/items/create');

    $page->click('Container')
        ->fill('#name', 'Toolbox')
        ->attach('[data-test=image-input]', base_path('database/seeders/sample-images/toolbox.jpg'))
        ->assertSee('Queued')
        ->assertNoJavaScriptErrors();
});

it('edits an item name and persists it', function () {
    $item = Item::factory()->create(['name' => 'Old Name']);

    $page = visit("/items/{$item->id}/edit");

    $page->assertValue('#name', 'Old Name')
        ->fill('#name', 'New Name')
        ->click('Save')
        ->assertPathIs("/items/{$item->id}")
        ->assertSee('New Name')
        ->assertNoJavaScriptErrors();

    expect($item->fresh()->name)->toBe('New Name');
});

it('hides detail fields for rooms and shows them for items', function () {
    $page = visit('/items/create');

    // Default type on a top-level create is Room — detail fields hidden.
    $page->assertSee('Tags')
        ->assertDontSee('Manufacturer')
        ->assertDontSee('Warranty')
        // Switch to Item — detail fields appear.
        ->click('Item')
        ->assertSee('Manufacturer')
        ->assertSee('Warranty')
        ->assertSee('Serial number')
        ->assertNoJavaScriptErrors();
});

it('captures detail fields when creating an item', function () {
    $page = visit('/items/create');

    $page->click('Item')
        ->fill('#name', 'Espresso machine')
        ->fill('#manufacturer', 'Breville')
        ->fill('#serial_number', 'SN-123456')
        ->fill('#purchase_price', '899.50')
        ->click('Create')
        ->assertSee('Espresso machine')
        ->assertSee('Breville')
        ->assertSee('SN-123456')
        ->assertSee('899,50') // de-DE / EUR formatting of the purchase price
        ->assertNoJavaScriptErrors();

    $item = Item::where('name', 'Espresso machine')->firstOrFail();
    expect($item->manufacturer)->toBe('Breville');
    expect($item->purchase_price)->toBe('899.50');
});

it('moves an item to a new parent via the move dialog', function () {
    $garage = Item::factory()->room()->create(['name' => 'Garage']);
    $kitchen = Item::factory()->room()->create(['name' => 'Kitchen']);
    $toolbox = Item::factory()->container()->create(['name' => 'Toolbox', 'parent_id' => $garage->id]);

    $page = visit("/items/{$toolbox->id}");

    $page->assertSee('Garage')
        ->click('@move-item')
        ->select('@move-target', (string) $kitchen->id)
        ->click('Move here')
        ->assertSee('Kitchen')
        ->assertNoJavaScriptErrors();

    expect($toolbox->fresh()->parent_id)->toBe($kitchen->id);
});

it('toggles a tag on while creating an item', function () {
    Tag::factory()->create(['name' => 'Tools']);

    $page = visit('/items/create');

    $page->fill('#name', 'Hammer')
        ->click('Tools')
        ->click('Create')
        ->assertSee('Hammer')
        ->assertSee('Tools')
        ->assertNoJavaScriptErrors();

    expect(Item::where('name', 'Hammer')->firstOrFail()->tags()->count())->toBe(1);
});
