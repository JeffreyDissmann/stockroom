<?php

declare(strict_types=1);

use App\Enums\ProposalField;
use App\Enums\ProposalStatus;
use App\Models\Item;
use App\Models\ItemImage;
use App\Models\ItemProposal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists what is waiting to be decided', function () {
    $item = Item::factory()->create(['name' => 'Moving box', 'description' => 'Winter coats.']);
    ItemProposal::factory()->for($item)->create(['current_value' => 'Winter coats.', 'proposed_value' => 'Winter coats and boots.']);
    ItemProposal::factory()->accepted()->create();

    $this->get('/proposals')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Proposals')
            ->has('proposals', 1)
            ->where('proposals.0.item.name', 'Moving box')
            ->where('proposals.0.suggestions.0.proposed_value', 'Winter coats and boots.')
            ->where('proposals.0.suggestions.0.is_stale', false)
        );
});

it('writes the suggestion onto the item when accepted', function () {
    $item = Item::factory()->create(['description' => 'Winter coats.']);
    $proposal = ItemProposal::factory()->for($item)->create([
        'current_value' => 'Winter coats.',
        'proposed_value' => 'Winter coats and a pair of boots.',
    ]);

    $this->patch("/proposals/{$proposal->id}")->assertRedirect();

    expect($item->fresh()->description)->toBe('Winter coats and a pair of boots.')
        ->and($proposal->fresh()->status)->toBe(ProposalStatus::Accepted);
});

it('records who decided and when', function () {
    // The audit trail is the point: months later, "where did this text come
    // from" has an answer.
    $user = User::factory()->create();
    $proposal = ItemProposal::factory()
        ->for(Item::factory()->create(['description' => null]))
        ->create(['current_value' => null]);

    $this->actingAs($user)->patch("/proposals/{$proposal->id}")->assertRedirect();

    expect($proposal->fresh()->reviewed_by)->toBe($user->id)
        ->and($proposal->fresh()->reviewed_at)->not->toBeNull();
});

it('leaves the item alone when dismissed', function () {
    $item = Item::factory()->create(['description' => 'Winter coats.']);
    $proposal = ItemProposal::factory()->for($item)->create(['proposed_value' => 'Something else entirely.']);

    $this->delete("/proposals/{$proposal->id}")->assertRedirect();

    expect($item->fresh()->description)->toBe('Winter coats.')
        ->and($proposal->fresh()->status)->toBe(ProposalStatus::Rejected);
});

it('keeps a dismissed suggestion rather than deleting it', function () {
    // Knowing something was already turned down is the only way to tell a fresh
    // idea from one that has been rejected before.
    $proposal = ItemProposal::factory()->create();

    $this->delete("/proposals/{$proposal->id}");

    expect(ItemProposal::find($proposal->id))->not->toBeNull();
});

it('refuses a suggestion the item has moved past', function () {
    // Someone edited by hand while this sat in the queue. Applying it now would
    // silently undo their edit.
    $item = Item::factory()->create(['description' => 'Winter coats.']);
    $proposal = ItemProposal::factory()->for($item)->create([
        'current_value' => 'Winter coats.',
        'proposed_value' => 'Winter coats and boots.',
    ]);

    $item->update(['description' => 'Summer things now.']);

    $this->patch("/proposals/{$proposal->id}")->assertSessionHasErrors('proposal');

    expect($item->fresh()->description)->toBe('Summer things now.')
        ->and($proposal->fresh()->status)->toBe(ProposalStatus::Pending);
});

it('flags a stale suggestion in the list so it is obvious before trying', function () {
    $item = Item::factory()->create(['description' => 'Winter coats.']);
    ItemProposal::factory()->for($item)->create(['current_value' => 'Winter coats.']);
    $item->update(['description' => 'Something else.']);

    $this->get('/proposals')
        ->assertInertia(fn ($page) => $page->where('proposals.0.suggestions.0.is_stale', true));
});

it('refuses a second decision on the same suggestion', function () {
    // Two people working the queue at once is ordinary in a shared household.
    $proposal = ItemProposal::factory()->accepted()->create();

    $this->patch("/proposals/{$proposal->id}")->assertSessionHasErrors('proposal');
    $this->delete("/proposals/{$proposal->id}")->assertSessionHasErrors('proposal');
});

it('accepts an identifier onto its own field', function () {
    $item = Item::factory()->create(['manufacturer' => null]);
    $proposal = ItemProposal::factory()->for($item)
        ->forField(ProposalField::Manufacturer, 'DeWalt')
        ->create(['current_value' => null]);

    $this->patch("/proposals/{$proposal->id}")->assertRedirect();

    expect($item->fresh()->manufacturer)->toBe('DeWalt');
});

it('is closed to guests', function () {
    auth()->logout();

    $this->get('/proposals')->assertRedirect('/login');
});

it('is open to members, since accepting is an ordinary item edit', function () {
    // Restricting this to admins would be stricter than the thing it stands in
    // for: members already edit items directly.
    $proposal = ItemProposal::factory()
        ->for(Item::factory()->create(['description' => null]))
        ->create(['current_value' => null]);

    $this->actingAs(User::factory()->create())
        ->patch("/proposals/{$proposal->id}")
        ->assertRedirect()
        ->assertSessionHasNoErrors();
});

it('groups several suggestions for one item into a single card', function () {
    // The unit a person reviews is the item: the photos and the context are
    // its own, and two suggestions about one box are one sitting-down.
    $item = Item::factory()->create(['description' => 'A box.', 'manufacturer' => null]);
    ItemProposal::factory()->for($item)->create(['current_value' => 'A box.']);
    ItemProposal::factory()->for($item)->forField(ProposalField::Manufacturer, 'Samla')->create(['current_value' => null]);

    $this->get('/proposals')
        ->assertInertia(fn ($page) => $page->has('proposals', 1)->has('proposals.0.suggestions', 2));
});

it('carries every photo so a photo suggestion can be judged from the list', function () {
    // Judging "the photo shows vintage cars" without seeing the photo is
    // rubber-stamping, which is the failure this whole design exists to avoid.
    // Every photo and not just the primary one: a suggestion is read from all
    // of them, so one thumbnail leaves most of the evidence unseen.
    $item = Item::factory()->create(['description' => null]);
    $primary = ItemImage::factory()->for($item)->primary()->create(['sort_order' => 0]);
    $second = ItemImage::factory()->for($item)->create(['sort_order' => 1]);
    ItemProposal::factory()->for($item)->create(['current_value' => null]);

    $this->get('/proposals')
        ->assertInertia(fn ($page) => $page
            ->has('proposals.0.item.images', 2)
            ->where('proposals.0.item.images.0.thumb_url', $primary->thumbUrl())
            ->where('proposals.0.item.images.1.thumb_url', $second->thumbUrl()));
});

it('shows an item its pending suggestions, keyed by the field each changes', function () {
    $item = Item::factory()->create(['description' => 'A box.', 'manufacturer' => null]);
    ItemProposal::factory()->for($item)->create(['current_value' => 'A box.']);
    ItemProposal::factory()->for($item)->forField(ProposalField::Manufacturer, 'Samla')->create(['current_value' => null]);
    ItemProposal::factory()->for($item)->accepted()->create();

    $this->get("/items/{$item->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('suggestions', 2)
            ->where('suggestions.manufacturer.proposed_value', 'Samla')
        );
});

it('loads an item with suggestions without tripping the lazy-loading guard', function () {
    // The presenter needs the item for its stale check. Letting each proposal
    // fetch its own is not an N+1 here but a hard failure, since the app runs
    // under Model::shouldBeStrict().
    $item = Item::factory()->create(['description' => null]);
    ItemProposal::factory()->for($item)->count(3)->create(['current_value' => null]);

    $this->get("/items/{$item->id}")->assertOk();
});
