<?php

declare(strict_types=1);

namespace Tests\Feature\Household;

use App\Enums\ItemType;
use App\Models\CustomField;
use App\Models\Item;
use App\Models\User;
use App\Services\Homebox\HomeboxClient;
use App\Services\Homebox\HomeboxImporter;
use App\Services\ItemImageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomeboxImportTest extends TestCase
{
    use RefreshDatabase;

    private const LOC = '11111111-1111-1111-1111-111111111111';

    private const ITEM = '22222222-2222-2222-2222-222222222222';

    private const ATT = '33333333-3333-3333-3333-333333333333';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_importer_maps_locations_items_tags_custom_fields_and_images(): void
    {
        $this->fakeHomebox();
        $result = $this->importer()->import();

        $this->assertSame(['entities' => 2, 'images' => 1, 'imagesSkipped' => 0, 'created' => 2, 'updated' => 0], $result);

        $garage = Item::where('name', 'Garage')->firstOrFail();
        $this->assertSame(ItemType::Room, $garage->type);

        $drill = Item::where('name', 'Drill')->firstOrFail();
        $this->assertSame(ItemType::Item, $drill->type);
        $this->assertSame($garage->id, $drill->parent_id);
        $this->assertSame('DeWalt', $drill->manufacturer);
        $this->assertSame('SN1', $drill->serial_number);
        $this->assertSame('99.50', $drill->purchase_price);
        $this->assertSame('2024-01-15', $drill->purchase_date->toDateString());
        $this->assertNull($drill->warranty_expires);   // Homebox year-0001 => empty
        $this->assertNull($drill->sold_to);
        $this->assertSame(2, $drill->quantity);
        $this->assertSame("A drill\n\nnote", $drill->description);

        $this->assertEqualsCanonicalizing(['Tools'], $drill->tags->pluck('name')->all());

        $values = $drill->customFieldValues()->with('field')->get()
            ->mapWithKeys(fn ($v) => [$v->field->name => $v->value]);
        $this->assertSame('A3', $values['Bin']);
        $this->assertSame('600', $values['Watts']);
        $this->assertSame('1', $values['Insured?']);
        $this->assertSame(self::ITEM, $values['Homebox ID']);

        $this->assertTrue(CustomField::where('key', 'homebox_id')->value('is_system'));

        $image = $drill->images()->firstOrFail();
        $this->assertTrue($image->is_primary);
        Storage::disk('public')->assertExists($image->thumbPath());
    }

    public function test_all_locations_including_nested_become_rooms(): void
    {
        $nested = 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa';

        Http::fake(function ($request) use ($nested) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            if (str_ends_with($path, '/entities/tree')) {
                return Http::response([[
                    'id' => self::LOC, 'name' => 'Keller', 'type' => 'location', 'children' => [
                        ['id' => $nested, 'name' => 'Kellerraum Rechts', 'type' => 'location', 'children' => []],
                    ],
                ]]);
            }

            return Http::response(['page' => 1, 'pageSize' => 100, 'total' => 0, 'items' => []]);
        });

        $this->importer()->import();

        $keller = Item::where('name', 'Keller')->firstOrFail();
        $right = Item::where('name', 'Kellerraum Rechts')->firstOrFail();

        $this->assertSame(ItemType::Room, $keller->type);
        $this->assertSame(ItemType::Room, $right->type);   // nested, but still a Room
        $this->assertSame($keller->id, $right->parent_id);
    }

    public function test_reimport_updates_instead_of_duplicating(): void
    {
        $this->fakeHomebox();
        $this->importer()->import();
        $result = $this->importer()->import();

        $this->assertSame(2, Item::count());
        $this->assertSame(['entities' => 2, 'images' => 1, 'imagesSkipped' => 0, 'created' => 0, 'updated' => 2], $result);
    }

    public function test_unreadable_images_are_skipped_without_aborting_the_import(): void
    {
        Http::fake(function ($request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            if (str_contains($path, '/attachments/')) {
                return Http::response('not-an-image', 200, ['Content-Type' => 'image/heic']);
            }
            if (str_ends_with($path, '/entities/tree')) {
                return Http::response([]);
            }
            if (preg_match('#/entities/([0-9a-f-]+)$#', $path, $m)) {
                return Http::response($this->detail($m[1]));
            }

            return Http::response([
                'page' => 1, 'pageSize' => 100, 'total' => 1,
                'items' => [['id' => self::ITEM, 'entityType' => ['isLocation' => false], 'parent' => null, 'tags' => []]],
            ]);
        });

        $result = $this->importer()->import();

        $this->assertSame(1, Item::count());
        $this->assertSame(0, $result['images']);
        $this->assertSame(1, $result['imagesSkipped']);
        $this->assertSame(0, Item::where('name', 'Drill')->firstOrFail()->images()->count());
    }

    public function test_start_endpoint_signs_in_and_runs_the_import(): void
    {
        $this->fakeHomebox();
        $this->actingAs(User::factory()->create())
            ->post('/household/import', ['url' => 'https://hb.test', 'username' => 'a@b.c', 'password' => 'secret'])
            ->assertRedirect();

        // Queue is sync in tests, so the job has already run.
        $this->assertSame(2, Item::count());
    }

    public function test_start_endpoint_reports_bad_credentials(): void
    {
        Http::fake(['*/users/login' => Http::response(['error' => 'nope'], 401)]);

        $this->actingAs(User::factory()->create())
            ->post('/household/import', ['url' => 'https://hb.test', 'username' => 'a@b.c', 'password' => 'wrong'])
            ->assertSessionHasErrors('connection');

        $this->assertSame(0, Item::count());
    }

    private function importer(): HomeboxImporter
    {
        return new HomeboxImporter(new HomeboxClient('https://hb.test', 'Bearer TESTTOKEN'), ItemImageProcessor::default());
    }

    private function fakeHomebox(): void
    {
        $file = UploadedFile::fake()->image('p.jpg', 80, 80);
        $jpeg = $file->getContent();

        Http::fake(function ($request) use ($jpeg) {
            $url = $request->url();
            $path = (string) parse_url($url, PHP_URL_PATH);

            if (str_contains($url, '/users/login')) {
                return Http::response(['token' => 'Bearer TESTTOKEN', 'expiresAt' => '2030-01-01T00:00:00Z', 'attachmentToken' => 'ATT']);
            }
            if (str_contains($path, '/attachments/')) {
                return Http::response($jpeg, 200, ['Content-Type' => 'image/jpeg']);
            }
            if (str_ends_with($path, '/entities/tree')) {
                return Http::response([['id' => self::LOC, 'name' => 'Garage', 'type' => 'location', 'children' => []]]);
            }
            if (preg_match('#/entities/([0-9a-f-]+)$#', $path, $m)) {
                return Http::response($this->detail($m[1]));
            }

            return Http::response([
                'page' => 1, 'pageSize' => 100, 'total' => 1,
                'items' => [
                    ['id' => self::ITEM, 'entityType' => ['isLocation' => false], 'parent' => ['id' => self::LOC], 'tags' => []],
                ],
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(string $id): array
    {
        if ($id === self::LOC) {
            return [
                'id' => self::LOC, 'name' => 'Garage', 'description' => '', 'notes' => '',
                'entityType' => ['isLocation' => true], 'parent' => null, 'tags' => [], 'fields' => [], 'attachments' => [],
            ];
        }

        return [
            'id' => self::ITEM, 'name' => 'Drill', 'description' => 'A drill', 'notes' => 'note', 'quantity' => 2,
            'entityType' => ['isLocation' => false], 'parent' => ['id' => self::LOC],
            'manufacturer' => 'DeWalt', 'modelNumber' => 'M1', 'serialNumber' => 'SN1',
            'purchaseFrom' => 'Store', 'purchaseDate' => '2024-01-15T00:00:00Z', 'purchasePrice' => 99.5,
            'lifetimeWarranty' => false, 'warrantyExpires' => '0001-01-01', 'warrantyDetails' => '',
            'soldTo' => '', 'soldPrice' => 0, 'soldDate' => '0001-01-01', 'soldNotes' => '',
            'tags' => [['id' => 't1', 'name' => 'Tools', 'color' => '#ffffff']],
            'fields' => [
                ['type' => 'text', 'name' => 'Bin', 'textValue' => 'A3', 'numberValue' => 0, 'booleanValue' => false],
                ['type' => 'number', 'name' => 'Watts', 'textValue' => '', 'numberValue' => 600, 'booleanValue' => false],
                ['type' => 'boolean', 'name' => 'Insured?', 'textValue' => '', 'numberValue' => 0, 'booleanValue' => true],
            ],
            'attachments' => [
                ['id' => self::ATT, 'type' => 'photo', 'primary' => true, 'title' => 'p.jpg', 'mimeType' => 'image/jpeg'],
            ],
        ];
    }
}
