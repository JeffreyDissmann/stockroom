<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('links two items via POST and writes both pivot rows', function () {
    $user = User::factory()->create();
    $a = Item::factory()->create(['name' => 'Camera']);
    $b = Item::factory()->create(['name' => 'Camera box']);

    $this->actingAs($user)
        ->post("/items/{$a->id}/related-items", ['related_item_id' => $b->id])
        ->assertRedirect();

    expect($a->fresh()->relatedItems->pluck('id')->all())->toContain($b->id)
        ->and($b->fresh()->relatedItems->pluck('id')->all())->toContain($a->id);
});

it('redirects guests to login on the link endpoint', function () {
    $a = Item::factory()->create();
    $b = Item::factory()->create();

    $this->post("/items/{$a->id}/related-items", ['related_item_id' => $b->id])
        ->assertRedirect('/login');
});

it('rejects a missing related item with a validation error', function () {
    $user = User::factory()->create();
    $a = Item::factory()->create();

    $this->actingAs($user)
        ->from("/items/{$a->id}")
        ->post("/items/{$a->id}/related-items", ['related_item_id' => 99999])
        ->assertSessionHasErrors('related_item_id');
});

it('surfaces the self-link guard as a validation error', function () {
    $user = User::factory()->create();
    $a = Item::factory()->create();

    $this->actingAs($user)
        ->from("/items/{$a->id}")
        ->post("/items/{$a->id}/related-items", ['related_item_id' => $a->id])
        ->assertSessionHasErrors('related_item_id');
});

it('is idempotent — POSTing the same link twice does not duplicate rows', function () {
    $user = User::factory()->create();
    $a = Item::factory()->create();
    $b = Item::factory()->create();

    $this->actingAs($user)->post("/items/{$a->id}/related-items", ['related_item_id' => $b->id]);
    $this->actingAs($user)->post("/items/{$a->id}/related-items", ['related_item_id' => $b->id]);

    expect(DB::table('item_relations')->count())->toBe(2);
});

it('unlinks via DELETE and removes both pivot rows', function () {
    $user = User::factory()->create();
    $a = Item::factory()->create();
    $b = Item::factory()->create();
    $a->linkRelated($b);

    $this->actingAs($user)
        ->delete("/items/{$a->id}/related-items/{$b->id}")
        ->assertRedirect();

    expect(DB::table('item_relations')->count())->toBe(0);
});

it('DELETE redirects guests to login', function () {
    $a = Item::factory()->create();
    $b = Item::factory()->create();

    $this->delete("/items/{$a->id}/related-items/{$b->id}")->assertRedirect('/login');
});

it('box creation auto-links the new box to the source item', function () {
    // Crucial integration with #9: the related-items link is the *durable*
    // connection. If the user later moves the box to the basement
    // (changing parent_id), the related link survives so the Show page
    // for the item still shows the box (and vice versa).
    $user = User::factory()->create();
    $phone = Item::factory()->create(['name' => 'iPhone 15 Pro']);

    $this->actingAs($user)->post("/items/{$phone->id}/box")->assertRedirect();

    $box = Item::query()->where('parent_id', $phone->id)->firstOrFail();

    expect($phone->fresh()->relatedItems->pluck('id')->all())->toContain($box->id)
        ->and($box->fresh()->relatedItems->pluck('id')->all())->toContain($phone->id);
});
