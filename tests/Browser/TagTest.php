<?php

declare(strict_types=1);

use App\Models\Tag;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('creates a tag from the inline form', function () {
    $page = visit('/tags');

    $page->assertSee('No tags yet.')
        ->type('#new-name', 'Electronics')
        ->click('Add tag')
        ->assertSee('Electronics')
        ->assertSee('0 items')
        ->assertNoJavaScriptErrors();

    expect(Tag::where('name', 'Electronics')->exists())->toBeTrue();
});

it('shows existing tags with their item counts', function () {
    Tag::factory()->create(['name' => 'Tools']);

    $page = visit('/tags');

    $page->assertSee('Tools')
        ->assertSee('Free-form labels')
        ->assertNoJavaScriptErrors();
});
