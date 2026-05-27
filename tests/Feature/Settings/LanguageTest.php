<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\ItemType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_language_page_renders(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/settings/language')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/Language')
                ->where('locale', 'en')
                ->has('locales.de')
            );
    }

    public function test_a_user_can_switch_to_german(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->patch('/settings/language', ['locale' => 'de'])->assertRedirect();

        $this->assertSame('de', $user->fresh()->locale);
    }

    public function test_an_unsupported_locale_is_rejected(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->patch('/settings/language', ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_guests_cannot_change_the_language(): void
    {
        $this->patch('/settings/language', ['locale' => 'de'])->assertRedirect('/login');
    }

    public function test_the_active_locale_and_translations_are_shared_to_the_frontend(): void
    {
        $user = User::factory()->create(['locale' => 'de']);

        $this->actingAs($user)->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('locale', 'de')
                ->has('translations')
            );
    }

    public function test_english_is_the_default_for_a_new_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page->where('locale', 'en'));
    }

    public function test_enum_labels_are_localised(): void
    {
        App::setLocale('de');
        $this->assertSame('Raum', ItemType::Room->label());

        App::setLocale('en');
        $this->assertSame('Room', ItemType::Room->label());
    }

    public function test_german_translations_resolve(): void
    {
        App::setLocale('de');

        $this->assertSame('Übersicht', __('nav.dashboard'));
        $this->assertSame('Speichern', __('common.save'));
    }

    public function test_validation_messages_come_out_in_german(): void
    {
        $user = User::factory()->create(['locale' => 'de']);

        $response = $this->actingAs($user)->post('/items', ['name' => '']);

        $response->assertSessionHasErrors('name');
        $this->assertStringContainsString('ausgefüllt', session('errors')->get('name')[0]);
    }
}
