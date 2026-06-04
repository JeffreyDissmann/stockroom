<?php

declare(strict_types=1);

use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\HomeAssistantLink;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const DEVICE_URL = 'https://home-assistant.dissmann.net/config/devices/device/fb0d054a0e7c035b297176db32aed45d';

function adoptValue(string $value, string $fieldName = 'Home Assistant', CustomFieldType $type = CustomFieldType::Url): Item
{
    $field = CustomField::factory()->create(['name' => $fieldName, 'type' => $type]);
    $item = Item::factory()->create();
    CustomFieldValue::create(['item_id' => $item->id, 'custom_field_id' => $field->id, 'value' => $value]);

    return $item;
}

it('fails when the named field is not found and lists what is available', function () {
    CustomField::factory()->create(['name' => 'Home Assistant', 'type' => CustomFieldType::Url]);

    $this->artisan('home-assistant:adopt-custom-field', ['field' => 'Nope'])
        ->expectsOutputToContain('not found')
        ->expectsOutputToContain('Home Assistant')
        ->assertFailed();
});

it('lists available fields when no argument is passed', function () {
    CustomField::factory()->create(['name' => 'Home Assistant', 'type' => CustomFieldType::Url]);

    $this->artisan('home-assistant:adopt-custom-field')
        ->expectsOutputToContain('No field specified')
        ->expectsOutputToContain('Home Assistant')
        ->assertSuccessful();
});

it('adopts device-page URL values into device-based links', function () {
    $item = adoptValue(DEVICE_URL);

    $this->artisan('home-assistant:adopt-custom-field', ['field' => 'Home Assistant'])->assertSuccessful();

    $link = HomeAssistantLink::query()->where('item_id', $item->id)->sole();
    expect($link->ha_device_id)->toBe('fb0d054a0e7c035b297176db32aed45d')
        ->and($link->ha_entity_id)->toBeNull()
        ->and($link->url)->toBe(DEVICE_URL);
});

it('adopts bare entity ids', function () {
    $item = adoptValue('sensor.living_room_tv', 'HA', CustomFieldType::Text);

    $this->artisan('home-assistant:adopt-custom-field', ['field' => 'HA'])->assertSuccessful();

    $link = HomeAssistantLink::query()->where('item_id', $item->id)->sole();
    expect($link->ha_entity_id)->toBe('sensor.living_room_tv')
        ->and($link->ha_device_id)->toBeNull();
});

it('auto-assigns and initialises the HomeAssistant tag like a live link', function () {
    $item = adoptValue(DEVICE_URL);

    $this->artisan('home-assistant:adopt-custom-field', ['field' => 'Home Assistant'])->assertSuccessful();

    $tag = Tag::query()->where('name', 'HomeAssistant')->sole();
    expect($item->fresh()->tags->contains($tag))->toBeTrue()
        ->and(Setting::int('home_assistant_tag_id'))->toBe($tag->id);
});

it('uses the configured tag when the household has chosen one', function () {
    $custom = Tag::factory()->create(['name' => 'Smart Home']);
    Setting::set('home_assistant_tag_id', $custom->id);
    $item = adoptValue(DEVICE_URL);

    $this->artisan('home-assistant:adopt-custom-field', ['field' => 'Home Assistant'])->assertSuccessful();

    expect($item->fresh()->tags->contains($custom))->toBeTrue()
        ->and(Tag::query()->where('name', 'HomeAssistant')->exists())->toBeFalse();
});

it('looks up the field by key as well as by name', function () {
    $field = CustomField::factory()->create(['name' => 'HA Device', 'key' => 'ha_device', 'type' => CustomFieldType::Url]);
    $item = Item::factory()->create();
    CustomFieldValue::create(['item_id' => $item->id, 'custom_field_id' => $field->id, 'value' => DEVICE_URL]);

    $this->artisan('home-assistant:adopt-custom-field', ['field' => 'ha_device'])->assertSuccessful();

    expect(HomeAssistantLink::query()->where('item_id', $item->id)->exists())->toBeTrue();
});

it('skips items that already have a link and never overwrites them', function () {
    $item = adoptValue(DEVICE_URL);
    HomeAssistantLink::factory()->create([
        'item_id' => $item->id,
        'ha_entity_id' => 'sensor.existing',
        'ha_device_id' => null,
        'url' => null,
    ]);

    $this->artisan('home-assistant:adopt-custom-field', ['field' => 'Home Assistant'])->assertSuccessful();

    $link = HomeAssistantLink::query()->where('item_id', $item->id)->sole();
    expect($link->ha_entity_id)->toBe('sensor.existing'); // untouched
});

it('is idempotent: re-running creates no duplicates', function () {
    $item = adoptValue(DEVICE_URL);

    $this->artisan('home-assistant:adopt-custom-field', ['field' => 'Home Assistant'])->assertSuccessful();
    $this->artisan('home-assistant:adopt-custom-field', ['field' => 'Home Assistant'])->assertSuccessful();

    expect(HomeAssistantLink::query()->where('item_id', $item->id)->count())->toBe(1);
});

it('reports unparseable values without aborting the rest of the batch', function () {
    $field = CustomField::factory()->create(['name' => 'HA', 'type' => CustomFieldType::Text]);
    $ok = Item::factory()->create();
    $bad = Item::factory()->create();
    CustomFieldValue::create(['item_id' => $ok->id, 'custom_field_id' => $field->id, 'value' => DEVICE_URL]);
    CustomFieldValue::create(['item_id' => $bad->id, 'custom_field_id' => $field->id, 'value' => 'just some text']);

    $this->artisan('home-assistant:adopt-custom-field', ['field' => 'HA'])
        ->expectsOutputToContain('could not parse')
        ->assertSuccessful();

    expect(HomeAssistantLink::query()->where('item_id', $ok->id)->count())->toBe(1)
        ->and(HomeAssistantLink::query()->where('item_id', $bad->id)->count())->toBe(0);
});

it('writes nothing on a dry run', function () {
    adoptValue(DEVICE_URL);

    $this->artisan('home-assistant:adopt-custom-field', ['field' => 'Home Assistant', '--dry-run' => true])
        ->expectsOutputToContain('Dry run')
        ->assertSuccessful();

    expect(HomeAssistantLink::query()->count())->toBe(0)
        ->and(Tag::query()->where('name', 'HomeAssistant')->exists())->toBeFalse();
});
