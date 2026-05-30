<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use App\Services\Backup\BackupExporter;
use App\Services\Backup\BackupImporter;
use App\Services\ItemImageProcessor;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;
use ZipArchive;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_system_page_requires_authentication(): void
    {
        $this->get('/household/backup')->assertRedirect('/login');
    }

    public function test_system_page_renders_for_an_authenticated_user(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/household/backup')
            ->assertOk();
    }

    public function test_export_route_requires_authentication(): void
    {
        $this->get('/household/backup/export')->assertRedirect('/login');
    }

    public function test_backup_archive_contains_the_manifest_data_and_original_images(): void
    {
        $room = Item::factory()->room()->create(['name' => 'Garage']);
        $tag = Tag::factory()->create(['name' => 'Tools']);
        $drill = Item::factory()->create([
            'type' => ItemType::Item,
            'name' => 'Drill',
            'parent_id' => $room->id,
            'purchase_price' => '99.50',
        ]);
        $drill->tags()->attach($tag);
        ItemImageProcessor::default()->store($drill, UploadedFile::fake()->image('drill.jpg', 640, 480));

        $path = app(BackupExporter::class)->export();

        $zip = new ZipArchive;
        $zip->open($path);

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $this->assertSame('stockroom-backup', $manifest['format']);
        $this->assertSame(1, $manifest['version']);
        // 2 tags: the seeded "Box" tag (from settings migration) plus "Tools" above.
        $this->assertSame(['tags' => 2, 'items' => 2, 'images' => 1], $manifest['counts']);

        $imageId = $drill->images()->value('id');
        $this->assertNotFalse($zip->locateName("images/{$imageId}/original.jpg"));
        $this->assertNotFalse($zip->locateName('data.json'));

        $zip->close();
        @unlink($path);
    }

    public function test_backup_round_trips_the_full_inventory(): void
    {
        $room = Item::factory()->room()->create(['name' => 'Office']);
        $tag = Tag::factory()->create(['name' => 'Electronics']);
        $laptop = Item::factory()->create([
            'type' => ItemType::Item,
            'name' => 'Laptop',
            'parent_id' => $room->id,
            'manufacturer' => 'Acme',
            'serial_number' => 'SN-123',
            'purchase_price' => '1299.00',
        ]);
        $laptop->tags()->attach($tag);

        $processor = ItemImageProcessor::default();
        $processor->store($laptop, UploadedFile::fake()->image('front.jpg', 800, 600));
        $processor->store($laptop, UploadedFile::fake()->image('back.jpg', 800, 600));

        $path = app(BackupExporter::class)->export();

        $this->wipeInventory();
        $this->assertSame(0, Item::count());

        $counts = app(BackupImporter::class)->import($path);
        // 2 tags: the seeded "Box" tag (from settings migration) plus "Workshop" above.
        $this->assertSame(['tags' => 2, 'items' => 2, 'images' => 2], $counts);

        $restoredRoom = Item::where('name', 'Office')->firstOrFail();
        $restoredLaptop = Item::where('name', 'Laptop')->firstOrFail();

        $this->assertSame($restoredRoom->id, $restoredLaptop->parent_id);
        $this->assertSame('Acme', $restoredLaptop->manufacturer);
        $this->assertSame('SN-123', $restoredLaptop->serial_number);
        $this->assertSame('1299.00', $restoredLaptop->purchase_price);
        $this->assertEqualsCanonicalizing(['Electronics'], $restoredLaptop->tags->pluck('name')->all());

        $images = $restoredLaptop->images()->orderBy('sort_order')->get();
        $this->assertCount(2, $images);
        $this->assertTrue($images[0]->is_primary);
        $this->assertFalse($images[1]->is_primary);

        foreach ($images as $image) {
            Storage::disk('public')->assertExists($image->originalPath());
            Storage::disk('public')->assertExists($image->largePath());
            Storage::disk('public')->assertExists($image->thumbPath());
        }

        @unlink($path);
    }

    public function test_a_restore_logs_added_items_without_logging_moves(): void
    {
        $room = Item::factory()->room()->create(['name' => 'Office']);
        Item::factory()->create(['type' => ItemType::Item, 'name' => 'Laptop', 'parent_id' => $room->id]);
        $path = app(BackupExporter::class)->export();

        $this->wipeInventory();
        Activity::query()->delete();

        app(BackupImporter::class)->import($path);

        // The restore shows up as normal "added" activity...
        $this->assertGreaterThan(0, Activity::where('event', 'created')->where('log_name', 'item')->count());

        // ...and wiring the hierarchy back up does not masquerade as a move.
        $this->assertFalse(
            Activity::where('event', 'updated')->get()->contains(
                fn (Activity $activity): bool => array_key_exists('parent.name', (array) ($activity->attribute_changes['attributes'] ?? []))
            ),
            'Restoring the hierarchy should not log location moves.'
        );
    }

    public function test_import_via_http_restores_and_reports_counts(): void
    {
        Item::factory()->room()->create(['name' => 'Shed']);
        Tag::factory()->create(['name' => 'Garden']);
        $path = app(BackupExporter::class)->export();
        $this->wipeInventory();

        $upload = new UploadedFile($path, 'backup.zip', 'application/zip', null, true);

        $this->actingAs(User::factory()->admin()->create())
            ->post('/household/backup/import', ['file' => $upload])
            ->assertRedirect()
            // 2 tags after a wipeInventory+restore: the backup re-creates its own "Box" tag
            // (it was in the export) plus whatever the source manifest carries.
            ->assertSessionHas('backup', ['tags' => 2, 'items' => 1, 'images' => 0]);

        $this->assertDatabaseHas('items', ['name' => 'Shed']);
    }

    public function test_import_rejects_a_non_zip_upload(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post('/household/backup/import', [
                'file' => UploadedFile::fake()->create('notes.txt', 5, 'text/plain'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_import_rejects_an_unrelated_archive(): void
    {
        $path = $this->makeZip(['readme.txt' => 'not a backup']);

        $this->expectException(ValidationException::class);

        try {
            app(BackupImporter::class)->import($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_import_rejects_a_newer_backup_version(): void
    {
        $path = $this->makeZip([
            'manifest.json' => json_encode(['format' => 'stockroom-backup', 'version' => 99]),
            'data.json' => json_encode(['tags' => [], 'items' => []]),
        ]);

        $this->expectException(ValidationException::class);

        try {
            app(BackupImporter::class)->import($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_a_failed_import_rolls_back_every_change(): void
    {
        $path = $this->makeZip([
            'manifest.json' => json_encode(['format' => 'stockroom-backup', 'version' => 1]),
            'data.json' => json_encode([
                'tags' => [['id' => 1, 'name' => 'Orphans', 'color' => null]],
                'items' => [[
                    'id' => 1, 'parent_id' => 999, 'type' => 'item', 'name' => 'Dangling',
                    'tags' => [], 'images' => [],
                ]],
            ]),
        ]);

        try {
            app(BackupImporter::class)->import($path);
            $this->fail('Expected the import to fail on the dangling parent reference.');
        } catch (QueryException) {
            // expected — the bad parent_id violates the foreign key.
        } finally {
            @unlink($path);
        }

        $this->assertSame(0, Item::count());
        // 1 surviving tag: the seeded "Box" pre-existed; the import's "Orphans"
        // tag was inserted inside the transaction that just rolled back.
        $this->assertSame(1, Tag::count());
    }

    private function wipeInventory(): void
    {
        Schema::disableForeignKeyConstraints();
        DB::table('item_tag')->delete();
        DB::table('item_images')->delete();
        DB::table('items')->delete();
        DB::table('tags')->delete();
        Schema::enableForeignKeyConstraints();
    }

    /**
     * @param  array<string, string>  $entries
     */
    private function makeZip(array $entries): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'stockroom-test-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $path;
    }
}
