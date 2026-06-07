<?php

declare(strict_types=1);

use App\Ai\Agents\ItemFieldExtractor;
use App\Models\HomeAssistantLink;
use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('shows the Home Assistant link read-only in the connections card on Show', function () {
    $item = Item::factory()->create(['name' => 'Cordless Drill']);
    HomeAssistantLink::factory()->create([
        'item_id' => $item->id,
        'friendly_name' => 'Drill',
        'url' => 'http://homeassistant.local:8123/config/devices/device/abc',
    ]);

    $page = visit("/items/{$item->id}");

    // Read-only on Show — the unlink control lives on the Edit page.
    $page->assertPresent('@connections-block')
        ->assertPresent('@ha-link-row')
        ->assertMissing('@ha-unlink')
        ->assertSee('Drill')
        ->assertNoJavaScriptErrors();
});

it('exposes the Home Assistant unlink control on the edit page', function () {
    $item = Item::factory()->create(['name' => 'Cordless Drill']);
    HomeAssistantLink::factory()->create(['item_id' => $item->id, 'friendly_name' => 'Drill']);

    $page = visit("/items/{$item->id}/edit");

    $page->assertPresent('@connections-edit-list')
        ->assertPresent('@ha-edit-row')
        ->assertPresent('@ha-unlink')
        ->assertSee('Drill')
        ->assertNoJavaScriptErrors();
});

it('shows the cached document title and type on the Paperless chip', function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'secret');

    $item = Item::factory()->create(['name' => 'Washing Machine']);
    $item->paperlessLinks()->create([
        'paperless_document_id' => 547,
        'document_title' => 'AEG receipt',
        'document_type' => 'Rechnung',
    ]);

    $page = visit("/items/{$item->id}");

    // The read-only Connections card shows title + type pill, not a bare #id.
    $page->assertSee('AEG receipt')
        ->assertSee('Rechnung')
        ->assertNoJavaScriptErrors();
});

it('groups Home Assistant and Paperless into one Connections section on edit', function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'secret');

    $item = Item::factory()->create(['name' => 'Cordless Drill']);
    $item->paperlessLinks()->create(['paperless_document_id' => 547]);
    HomeAssistantLink::factory()->create(['item_id' => $item->id, 'friendly_name' => 'Drill']);

    $page = visit("/items/{$item->id}/edit");

    // Both links live under a single "Connections" list, each with its own unlink.
    $page->assertSee('Connections')
        ->assertPresent('@connections-edit-list')
        ->assertPresent('@ha-unlink')
        ->assertPresent('@paperless-unlink-547')
        ->assertNoJavaScriptErrors();
});

it('offers the link-document dialog in Connections when Paperless is enabled', function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'secret');

    $item = Item::factory()->create(['name' => 'Cordless Drill']);

    $page = visit("/items/{$item->id}/edit");

    // The section shows even with no existing links — it hosts the trigger.
    // Typing a bare id in the dialog offers the direct "Link document #447"
    // row (no Paperless round-trip; ids skip the admin-only search).
    $page->assertSee('Connections')
        ->assertPresent('@paperless-add')
        ->click('@paperless-add')
        ->assertPresent('@paperless-add-input')
        ->fill('@paperless-add-input', '447')
        ->assertPresent('@paperless-add-direct')
        ->assertPresent('@paperless-add-submit')
        ->assertNoJavaScriptErrors();
});

it('hides the link-document trigger when Paperless is disabled', function () {
    config()->set('paperless.url', '');

    $item = Item::factory()->create(['name' => 'Cordless Drill']);
    HomeAssistantLink::factory()->create(['item_id' => $item->id, 'friendly_name' => 'Drill']);

    $page = visit("/items/{$item->id}/edit");

    // The HA link still renders the section; the Paperless trigger does not.
    $page->assertPresent('@connections-edit-list')
        ->assertMissing('@paperless-add')
        ->assertNoJavaScriptErrors();
});

it('fills empty fields and proposes overrides when suggesting from a document', function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'secret');
    config()->set('ai.enabled', true);

    Http::fake([
        'https://paperless.test/api/documents/547/' => Http::response([
            'id' => 547,
            'content' => 'BOSCH GSR 12V-35 ... receipt text',
        ]),
    ]);
    ItemFieldExtractor::fake([[
        'name' => 'Bosch GSR 12V-35',
        'manufacturer' => 'Bosch',
    ]]);

    $item = Item::factory()->create(['name' => 'Cordless Drill', 'manufacturer' => null]);
    $item->paperlessLinks()->create(['paperless_document_id' => 547]);

    $page = visit("/items/{$item->id}/edit");

    // Empty manufacturer fills directly (with the suggested badge); the
    // conflicting name is NOT overwritten — it renders as an explicit
    // "Document says" chip whose Apply button performs the override.
    $page->assertPresent('@paperless-suggest-547')
        ->click('@paperless-suggest-547')
        ->assertValue('#manufacturer', 'Bosch')
        ->assertValue('#name', 'Cordless Drill')
        ->assertPresent('@doc-proposal-name')
        ->click('@doc-proposal-apply-name')
        ->assertValue('#name', 'Bosch GSR 12V-35')
        ->assertMissing('@doc-proposal-name')
        ->assertNoJavaScriptErrors();
});

it('shows a single Find image trigger inside the image panel on edit', function () {
    config()->set('services.brave.key', 'test-key');

    $item = Item::factory()->create(['name' => 'Cordless Drill']);

    $page = visit("/items/{$item->id}/edit");

    // The "or Find image" row under the drop zone is THE trigger — the
    // standalone duplicate below the panel was removed (2026-06-07).
    $page->assertPresent('@image-search')
        ->assertNoJavaScriptErrors();

    expect($page->script('document.querySelectorAll(\'[data-test="image-search"]\').length'))->toBe(1);
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
        ->click('Kitchen') // pick the Kitchen destination row in the move dialog
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
