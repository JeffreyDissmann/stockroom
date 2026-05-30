<?php

declare(strict_types=1);

use App\Models\Item;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('attaches a paperless document via the relation', function () {
    $item = Item::factory()->create();

    $link = $item->paperlessLinks()->create(['paperless_document_id' => 42]);

    expect($link->paperless_document_id)->toBe(42)
        ->and($link->item_id)->toBe($item->id)
        ->and($item->fresh()->paperlessLinks->pluck('paperless_document_id')->all())->toEqual([42]);
});

it('cascades deletes — destroying the item removes its paperless links', function () {
    $item = Item::factory()->create();
    $item->paperlessLinks()->create(['paperless_document_id' => 42]);

    $item->delete();

    expect(DB::table('paperless_links')->count())->toBe(0);
});

it('rejects duplicate (item, doc) pairs (unique constraint)', function () {
    $item = Item::factory()->create();
    $item->paperlessLinks()->create(['paperless_document_id' => 42]);

    expect(fn () => $item->paperlessLinks()->create(['paperless_document_id' => 42]))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows the same doc to be linked to different items (1 doc → N items)', function () {
    $a = Item::factory()->create();
    $b = Item::factory()->create();

    $a->paperlessLinks()->create(['paperless_document_id' => 99]);
    $b->paperlessLinks()->create(['paperless_document_id' => 99]);

    expect(DB::table('paperless_links')->where('paperless_document_id', 99)->count())->toBe(2);
});

it('paperlessUrl() composes the click-through URL from config', function () {
    config()->set('paperless.url', 'https://paperless.test');

    $item = Item::factory()->create();
    $link = $item->paperlessLinks()->create(['paperless_document_id' => 42]);

    expect($link->paperlessUrl())->toBe('https://paperless.test/documents/42/');
});

it('paperlessUrl() returns null when the integration is disabled', function () {
    config()->set('paperless.url', '');

    $item = Item::factory()->create();
    $link = $item->paperlessLinks()->create(['paperless_document_id' => 42]);

    expect($link->paperlessUrl())->toBeNull();
});
