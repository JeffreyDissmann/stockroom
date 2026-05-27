<?php

declare(strict_types=1);

namespace Tests\Feature\Items;

use App\Ai\Agents\ItemPhotoAnalyzer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ItemPhotoAnalysisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.enabled' => true]);
    }

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->image('item.jpg', 200, 200);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function analyze(array $data): TestResponse
    {
        // A real multipart POST (UploadedFile), but Accept: application/json so
        // validation failures come back as 422 JSON rather than a redirect.
        return $this->post('/items/analyze-photo', $data, ['Accept' => 'application/json']);
    }

    public function test_it_requires_authentication(): void
    {
        $this->analyze(['photo' => $this->photo()])->assertUnauthorized();
    }

    public function test_it_returns_extracted_fields_from_the_vision_agent(): void
    {
        ItemPhotoAnalyzer::fake([[
            'name' => 'DeWalt 20V Drill',
            'manufacturer' => 'DeWalt',
            'model_number' => 'DCD777',
            'serial_number' => null,
            'description' => 'A cordless power drill.',
        ]]);

        $this->actingAs(User::factory()->create())
            ->analyze(['photo' => $this->photo()])
            ->assertOk()
            ->assertJson(['fields' => [
                'name' => 'DeWalt 20V Drill',
                'manufacturer' => 'DeWalt',
                'model_number' => 'DCD777',
                'serial_number' => null,
                'description' => 'A cordless power drill.',
            ]]);

        ItemPhotoAnalyzer::assertPrompted(fn () => true);
    }

    public function test_it_prompts_for_name_and_description_in_the_users_language(): void
    {
        ItemPhotoAnalyzer::fake([['name' => 'Bohrmaschine', 'description' => 'Eine Akku-Bohrmaschine.']]);

        $this->actingAs(User::factory()->create(['locale' => 'de']))
            ->analyze(['photo' => $this->photo()])
            ->assertOk();

        ItemPhotoAnalyzer::assertPrompted(fn ($prompt) => str_contains($prompt->agent->instructions(), 'in German'));
    }

    public function test_it_defaults_to_english_for_an_english_user(): void
    {
        ItemPhotoAnalyzer::fake([['name' => 'Drill']]);

        $this->actingAs(User::factory()->create(['locale' => 'en']))
            ->analyze(['photo' => $this->photo()])
            ->assertOk();

        ItemPhotoAnalyzer::assertPrompted(fn ($prompt) => str_contains($prompt->agent->instructions(), 'in English'));
    }

    public function test_it_trims_values_blanks_to_null_and_only_returns_known_keys(): void
    {
        ItemPhotoAnalyzer::fake([[
            'name' => '  Gold Bar  ',
            'manufacturer' => '',
            'description' => 'A 1kg bar.',
            'unexpected' => 'should be dropped',
        ]]);

        $response = $this->actingAs(User::factory()->create())
            ->analyze(['photo' => $this->photo()])
            ->assertOk()
            ->assertJsonPath('fields.name', 'Gold Bar')       // trimmed
            ->assertJsonPath('fields.manufacturer', null)     // blank → null
            ->assertJsonPath('fields.model_number', null)     // missing → null
            ->assertJsonPath('fields.serial_number', null)
            ->assertJsonPath('fields.description', 'A 1kg bar.');

        $this->assertEqualsCanonicalizing(
            ['name', 'manufacturer', 'model_number', 'serial_number', 'description'],
            array_keys($response->json('fields')),
        );
    }

    public function test_it_requires_a_photo(): void
    {
        $this->actingAs(User::factory()->create())
            ->analyze([])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }

    public function test_it_rejects_a_non_image_file(): void
    {
        $this->actingAs(User::factory()->create())
            ->analyze(['photo' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }

    public function test_it_is_gated_by_the_ai_enabled_flag(): void
    {
        config(['ai.enabled' => false]);

        ItemPhotoAnalyzer::fake([['name' => 'Should never run']]);

        $this->actingAs(User::factory()->create())
            ->analyze(['photo' => $this->photo()])
            ->assertStatus(503);

        ItemPhotoAnalyzer::assertNeverPrompted();
    }
}
