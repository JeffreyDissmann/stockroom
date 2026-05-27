<?php

declare(strict_types=1);

use App\Ai\Agents\InventoryAssistant;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['name' => 'Jordan Diss']));
});

it('loads the profile settings page', function () {
    $page = visit('/settings/profile');

    $page->assertSee('Profile information')
        ->assertValue('#name', 'Jordan Diss')
        ->assertNoJavaScriptErrors();
});

it('loads the password settings page', function () {
    $page = visit('/settings/password');

    $page->assertSee('Update password')
        ->assertPresent('#current_password')
        ->assertPresent('#password')
        ->assertNoJavaScriptErrors();
});

it('shows the bottom tab bar on a mobile viewport', function () {
    $page = visit('/dashboard')->on()->iPhone14Pro();

    $page->assertVisible('.bottom-tabs')
        ->assertSee('More')
        ->assertNoJavaScriptErrors();
});

it('opens the assistant from the mobile more menu', function () {
    $page = visit('/dashboard')->on()->iPhone14Pro();

    $page->click('More')
        ->click('Assistant')
        ->assertSee('Ask me where something is') // the panel's empty state
        ->assertPresent('@assistant-new') // the "New chat" reset control
        ->assertNoJavaScriptErrors();
});

it('keeps a reset thread empty after a page reload', function () {
    // Seed an existing conversation so the panel has something to rehydrate.
    $conversationId = (string) Str::uuid();
    Conversation::create(['id' => $conversationId, 'user_id' => auth()->id(), 'title' => 'Old thread']);
    ConversationMessage::create([
        'id' => (string) Str::uuid(),
        'conversation_id' => $conversationId,
        'user_id' => auth()->id(),
        'agent' => InventoryAssistant::class,
        'role' => 'user',
        'content' => 'where is the cordless drill',
        'attachments' => [], 'tool_calls' => [], 'tool_results' => [], 'usage' => [], 'meta' => [],
    ]);

    $page = visit('/dashboard');
    $page->click('@open-assistant')
        ->assertSee('where is the cordless drill'); // old thread is rehydrated

    // Simulate a reset (the "New chat" flag) and reload: the old thread must not return.
    $page->script("localStorage.setItem('assistant:fresh', '1')");
    $page->refresh();

    $page->click('@open-assistant')
        ->assertSee('Ask me where something is') // empty state instead
        ->assertDontSee('where is the cordless drill')
        ->assertNoJavaScriptErrors();
});
