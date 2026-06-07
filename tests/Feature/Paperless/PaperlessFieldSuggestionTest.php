<?php

declare(strict_types=1);

use App\Ai\Agents\ItemFieldExtractor;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('paperless.url', 'https://paperless.test');
    config()->set('paperless.token', 'TOKEN');
    config()->set('ai.enabled', true);

    $this->actingAs(User::factory()->create());

    $this->item = Item::factory()->create(['name' => 'Washing Machine']);
    $this->item->paperlessLinks()->create(['paperless_document_id' => 447]);
});

function fakeDocumentContent(string $content): void
{
    Http::fake([
        'https://paperless.test/api/documents/447/' => Http::response([
            'id' => 447,
            'title' => 'Receipt',
            'content' => $content,
        ]),
    ]);
}

it('proposes normalised field values from the linked document', function () {
    fakeDocumentContent('BOSCH WGB256040 Serie 8 ... EUR 849.00 ... 12.03.2024 MediaMarkt');
    ItemFieldExtractor::fake([[
        'name' => '  Bosch Serie 8 WGB256040  ',
        'manufacturer' => 'Bosch',
        'model_number' => 'WGB256040',
        'serial_number' => null,
        'purchase_price' => '849.00',
        'purchase_date' => '2024-03-12',
        'purchased_from' => 'MediaMarkt',
        'quantity' => 1,
        'description' => '',
    ]]);

    $response = $this->postJson("/items/{$this->item->id}/paperless-links/447/suggest-fields")
        ->assertOk()
        ->json('fields');

    expect($response)->toBe([
        'name' => 'Bosch Serie 8 WGB256040',
        'manufacturer' => 'Bosch',
        'model_number' => 'WGB256040',
        'serial_number' => null,
        'purchased_from' => 'MediaMarkt',
        'description' => null,
        // 849.0 leaves the controller as float; JSON serialisation drops the
        // trailing .0, so it arrives as int 849.
        'purchase_price' => 849,
        'quantity' => 1,
        'purchase_date' => '2024-03-12',
    ]);

    // The agent is told which item the document belongs to and gets the OCR.
    ItemFieldExtractor::assertPrompted(fn ($prompt) => str_contains($prompt->agent->instructions(), 'Washing Machine')
        && str_contains((string) $prompt->prompt, 'BOSCH WGB256040'));
});

it('normalises junk model output to null', function () {
    fakeDocumentContent('some text');
    ItemFieldExtractor::fake([[
        'purchase_price' => -5,
        'quantity' => 0,
        'purchase_date' => 'sometime in 2023',
        'manufacturer' => '   ',
    ]]);

    $fields = $this->postJson("/items/{$this->item->id}/paperless-links/447/suggest-fields")
        ->assertOk()
        ->json('fields');

    expect($fields['purchase_price'])->toBeNull()
        ->and($fields['quantity'])->toBeNull()
        ->and($fields['purchase_date'])->toBeNull()
        ->and($fields['manufacturer'])->toBeNull();
});

it('404s for a document that is not linked to the item', function () {
    // Indistinguishable from a nonexistent document — membership is the
    // permission boundary for reading linked-document content.
    $this->postJson("/items/{$this->item->id}/paperless-links/999/suggest-fields")
        ->assertNotFound();
});

it('422s when the document has no readable text', function () {
    fakeDocumentContent('   ');

    $this->postJson("/items/{$this->item->id}/paperless-links/447/suggest-fields")
        ->assertStatus(422);
});

it('502s when Paperless is unreachable', function () {
    Http::fake([
        'https://paperless.test/api/documents/447/' => Http::response([], 500),
    ]);

    $this->postJson("/items/{$this->item->id}/paperless-links/447/suggest-fields")
        ->assertStatus(502);
});

it('is gated by the Paperless and AI feature flags', function () {
    config()->set('paperless.url', '');
    $this->postJson("/items/{$this->item->id}/paperless-links/447/suggest-fields")
        ->assertNotFound();

    config()->set('paperless.url', 'https://paperless.test');
    config()->set('ai.enabled', false);
    $this->postJson("/items/{$this->item->id}/paperless-links/447/suggest-fields")
        ->assertStatus(503);
});
