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

it('loads the API tokens settings page', function () {
    $page = visit('/settings/api-tokens');

    $page->assertSee('API tokens')
        ->assertPresent('@api-token-create')
        ->assertNoJavaScriptErrors();
});

it('creates an API token and shows it once', function () {
    $page = visit('/settings/api-tokens');

    // Name + the default `read` ability is enough; on success the one-time
    // plaintext banner appears and the new token is listed.
    $page->fill('#token_name', 'My HA token')
        ->click('@api-token-create')
        ->assertPresent('@api-token-plaintext')
        ->assertSee('My HA token')
        ->assertNoJavaScriptErrors();
});

it('shows the bottom tab bar on a mobile viewport', function () {
    $page = visit('/dashboard')->on()->iPhone14Pro();

    $page->assertVisible('.bottom-tabs')
        ->assertSee('More')
        ->assertNoJavaScriptErrors();
});

it('does not leak raw translation keys in the mobile more menu', function () {
    // The HomeBox import was consolidated into the Backup screen; the legacy
    // "household.nav.import" lang key was deleted, but the BottomTabs menu kept
    // an entry pointing at the redirect — so the dropdown rendered the raw key.
    $page = visit('/dashboard')->on()->iPhone14Pro();

    $page->click('@open-more')
        ->assertDontSee('household.nav.import')
        ->assertDontSee('nav.import')
        ->assertNoJavaScriptErrors();
});

it('opens the assistant from the mobile more menu', function () {
    $page = visit('/dashboard')->on()->iPhone14Pro();

    $page->click('@open-more')
        ->click('@open-assistant-mobile')
        ->assertSee('Ask me where something is') // the panel's empty state
        ->assertPresent('@assistant-new') // the "New chat" reset control
        ->assertPresent('@assistant-attach') // the image-attach control
        ->assertNoJavaScriptErrors();
});

it('shows a floating assistant button on mobile and opens the panel from it', function () {
    $page = visit('/dashboard')->on()->iPhone14Pro();

    $page->click('@open-assistant-fab')
        ->assertSee('Ask me where something is')
        ->assertNoJavaScriptErrors();
});

it('hides the floating assistant button on desktop', function () {
    // The FAB is mobile-only. A scoped <style> rule used to override Tailwind's
    // `md:hidden` on specificity, leaking the button onto desktop; this test
    // pins that behaviour down so a future style edit doesn't reintroduce it.
    $page = visit('/dashboard');

    $page->assertMissing('@open-assistant-fab')
        ->assertNoJavaScriptErrors();
});

it('opens the assistant with the keyboard shortcut', function () {
    $page = visit('/dashboard');

    // Press ⌘/Ctrl+⇧+A on a focusable, hydrated element; the keydown bubbles to
    // the panel's global listener and toggles it open. Using a real element
    // (rather than a synthetic dispatch) waits for Vue to mount its listener.
    $page->keys('@open-search', 'Control+Shift+A')
        ->assertSee('Ask me where something is')
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
