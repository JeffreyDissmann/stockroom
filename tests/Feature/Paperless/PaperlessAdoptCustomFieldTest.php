<?php

declare(strict_types=1);

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Item;
use App\Models\PaperlessLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('fails when the named field is not found and lists what is available', function () {
    CustomField::factory()->create(['name' => 'Paperless URL', 'type' => CustomFieldType::Url]);

    $this->artisan('paperless:adopt-custom-field', ['field' => 'Nope'])
        ->expectsOutputToContain('not found')
        ->expectsOutputToContain('Paperless URL')
        ->assertFailed();
});

it('lists available fields when no argument is passed', function () {
    CustomField::factory()->create(['name' => 'Paperless URL', 'type' => CustomFieldType::Url]);
    CustomField::factory()->create(['name' => 'Manual']);

    $this->artisan('paperless:adopt-custom-field')
        ->expectsOutputToContain('No field specified')
        ->expectsOutputToContain('Paperless URL')
        ->expectsOutputToContain('Manual')
        ->assertSuccessful();
});

it('adopts numeric doc-id values', function () {
    $field = CustomField::factory()->create(['name' => 'Paperless', 'type' => CustomFieldType::Text]);
    $item = Item::factory()->create();

    CustomFieldValue::create(['item_id' => $item->id, 'custom_field_id' => $field->id, 'value' => '447']);

    $this->artisan('paperless:adopt-custom-field', ['field' => 'Paperless'])
        ->assertSuccessful();

    expect(PaperlessLink::query()->where('item_id', $item->id)->pluck('paperless_document_id')->all())
        ->toBe([447]);
});

it('adopts URL values by extracting the document id from the path', function () {
    $field = CustomField::factory()->create(['name' => 'Rechnung Paperless', 'type' => CustomFieldType::Url]);
    $item = Item::factory()->create();

    CustomFieldValue::create([
        'item_id' => $item->id,
        'custom_field_id' => $field->id,
        'value' => 'https://paperless.example.test/documents/547/',
    ]);

    $this->artisan('paperless:adopt-custom-field', ['field' => 'Rechnung Paperless'])
        ->assertSuccessful();

    expect(PaperlessLink::query()->where('item_id', $item->id)->pluck('paperless_document_id')->all())
        ->toBe([547]);
});

it('looks up the field by key as well as by name', function () {
    $field = CustomField::factory()->create(['name' => 'Paperless Receipt', 'key' => 'paperless_receipt', 'type' => CustomFieldType::Url]);
    $item = Item::factory()->create();

    CustomFieldValue::create([
        'item_id' => $item->id,
        'custom_field_id' => $field->id,
        'value' => 'https://paperless.example/documents/12/',
    ]);

    $this->artisan('paperless:adopt-custom-field', ['field' => 'paperless_receipt'])
        ->assertSuccessful();

    expect(PaperlessLink::query()->where('paperless_document_id', 12)->exists())->toBeTrue();
});

it('is idempotent: re-running does not create duplicates', function () {
    $field = CustomField::factory()->create(['name' => 'Paperless', 'type' => CustomFieldType::Text]);
    $item = Item::factory()->create();

    CustomFieldValue::create(['item_id' => $item->id, 'custom_field_id' => $field->id, 'value' => '100']);

    $this->artisan('paperless:adopt-custom-field', ['field' => 'Paperless'])->assertSuccessful();
    $this->artisan('paperless:adopt-custom-field', ['field' => 'Paperless'])->assertSuccessful();

    expect(PaperlessLink::query()->where('item_id', $item->id)->count())->toBe(1);
});

it('reports unparseable values without aborting the rest of the batch', function () {
    $field = CustomField::factory()->create(['name' => 'Paperless', 'type' => CustomFieldType::Text]);
    $itemOk = Item::factory()->create();
    $itemBad = Item::factory()->create();

    CustomFieldValue::create(['item_id' => $itemOk->id, 'custom_field_id' => $field->id, 'value' => '200']);
    CustomFieldValue::create(['item_id' => $itemBad->id, 'custom_field_id' => $field->id, 'value' => 'not a link or id']);

    $this->artisan('paperless:adopt-custom-field', ['field' => 'Paperless'])
        ->expectsOutputToContain('could not parse')
        ->assertSuccessful();

    expect(PaperlessLink::query()->where('item_id', $itemOk->id)->count())->toBe(1)
        ->and(PaperlessLink::query()->where('item_id', $itemBad->id)->count())->toBe(0);
});

it('runs the re-link job synchronously when --relink is passed and Paperless is configured', function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'TOKEN');
    config()->set('paperless.trigger_tag', 'Add to Stockroom');
    config()->set('paperless.linked_tag', 'Stockroom');
    config()->set('paperless.link_custom_field', 'Stockroom URL');
    config()->set('app.url', 'https://stockroom.test');

    $field = CustomField::factory()->create(['name' => 'Paperless', 'type' => CustomFieldType::Text]);
    $item = Item::factory()->create();
    CustomFieldValue::create(['item_id' => $item->id, 'custom_field_id' => $field->id, 'value' => '300']);

    Http::fake(function ($request) {
        $url = $request->url();
        $method = $request->method();

        if (str_contains($url, '/api/tags/') && str_contains($url, 'Add%20to%20Stockroom')) {
            return Http::response(['results' => [['id' => 9, 'name' => 'Add to Stockroom']]]);
        }
        if (str_contains($url, '/api/tags/') && str_contains($url, 'Stockroom')) {
            return Http::response(['results' => [['id' => 10, 'name' => 'Stockroom']]]);
        }
        if (str_contains($url, '/api/custom_fields/')) {
            return Http::response(['results' => [['id' => 5, 'name' => 'Stockroom URL']]]);
        }
        if (str_contains($url, '/api/documents/300/')) {
            return $method === 'GET'
                ? Http::response(['id' => 300, 'tags' => [], 'custom_fields' => []])
                : Http::response([], 200);
        }

        return Http::response([], 404);
    });

    $this->artisan('paperless:adopt-custom-field', ['field' => 'Paperless', '--relink' => true])
        ->expectsOutputToContain('Re-link complete')
        ->assertSuccessful();

    Http::assertSent(fn ($r) => $r->method() === 'PATCH'
        && str_contains($r->url(), '/api/documents/300/'));
});

it('skips the --relink phase when Paperless is not configured', function () {
    config()->set('paperless.url', '');

    $field = CustomField::factory()->create(['name' => 'Paperless', 'type' => CustomFieldType::Text]);
    $item = Item::factory()->create();
    CustomFieldValue::create(['item_id' => $item->id, 'custom_field_id' => $field->id, 'value' => '404']);

    Http::preventStrayRequests();
    Http::fake();

    $this->artisan('paperless:adopt-custom-field', ['field' => 'Paperless', '--relink' => true])
        ->expectsOutputToContain('not configured')
        ->assertSuccessful();

    Http::assertNothingSent();
});
