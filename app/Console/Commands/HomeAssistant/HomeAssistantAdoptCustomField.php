<?php

declare(strict_types=1);

namespace App\Console\Commands\HomeAssistant;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Item;
use App\Services\Items\HomeAssistantLinker;
use Illuminate\Console\Command;

/**
 * Migrates pre-existing manual Home Assistant references stored on a custom
 * field into proper `home_assistant_links` rows.
 *
 * Before this integration shipped, an item's Home Assistant device was often
 * recorded by hand in a custom field — typically the device-page URL, e.g.
 * `https://home-assistant.example/config/devices/device/fb0d054a0e7c…`. This
 * command walks one such field, parses each value, and creates the matching
 * link through HomeAssistantLinker — so each adopted item gets the same
 * auto-assigned "HomeAssistant" tag the API would give it (the tag is created
 * and selected on the first adoption, exactly like a live link).
 *
 * Parser accepts:
 *   - a device-page URL `…/config/devices/device/{id}` → device id + the URL
 *   - a bare entity id `domain.object_id` (e.g. `sensor.living_room_tv`)
 *
 * Idempotent and non-destructive: items that already have a link are skipped
 * (never overwritten), and the source custom-field values are left in place.
 */
class HomeAssistantAdoptCustomField extends Command
{
    protected $signature = 'home-assistant:adopt-custom-field
        {field? : Custom field name or key holding the legacy Home Assistant link. Omit to list available fields.}
        {--dry-run : Show what would be linked without writing anything}';

    protected $description = 'Migrate manually-stored Home Assistant references on a custom field into proper home_assistant_links rows';

    public function handle(HomeAssistantLinker $linker): int
    {
        $needle = $this->argument('field');

        if ($needle === null) {
            $this->info('No field specified. Pass one of these as the first argument:');
            $this->listAvailableFields();

            return self::SUCCESS;
        }

        $field = CustomField::query()
            ->where('name', $needle)
            ->orWhere('key', $needle)
            ->first();

        if ($field === null) {
            $this->error("Custom field '{$needle}' not found (looked up by name and key).");
            $this->newLine();
            $this->line('Available fields:');
            $this->listAvailableFields();

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry run — no links or tags will be created.');
        }

        $this->info("Adopting Home Assistant links from custom field <fg=cyan>{$field->name}</> (id={$field->id}, type={$field->type->value})…");

        $created = 0;
        $skipped = 0;
        $unparseable = 0;

        CustomFieldValue::query()
            ->where('custom_field_id', $field->id)
            ->chunkById(200, function ($values) use (&$created, &$skipped, &$unparseable, $linker, $dryRun): void {
                foreach ($values as $value) {
                    $attributes = $this->parseLink((string) $value->value);

                    if ($attributes === null) {
                        $unparseable++;
                        $this->components->twoColumnDetail(
                            "item #{$value->item_id}",
                            '<fg=yellow>could not parse</> <fg=gray>'.trim((string) $value->value).'</>',
                        );

                        continue;
                    }

                    $item = Item::query()->find($value->item_id);

                    if ($item === null) {
                        // Orphaned value (item deleted) — nothing to link.
                        $skipped++;

                        continue;
                    }

                    $label = $attributes['ha_device_id'] ?? $attributes['ha_entity_id'];

                    // Never overwrite a link the user already has (e.g. from a
                    // live integration link). Adoption only fills the gaps.
                    if ($item->homeAssistantLink()->exists()) {
                        $skipped++;

                        continue;
                    }

                    if ($dryRun) {
                        $created++;
                        $this->components->twoColumnDetail("item #{$value->item_id}", "<fg=blue>would link → {$label}</>");

                        continue;
                    }

                    $linker->link($item, $attributes);
                    $created++;
                    $this->components->twoColumnDetail("item #{$value->item_id}", "<fg=green>linked → {$label}</>");
                }
            });

        $this->newLine();
        $this->components->twoColumnDetail($dryRun ? 'Would create' : 'Created', "<fg=green>{$created}</>");
        $this->components->twoColumnDetail('Already linked (skipped)', (string) $skipped);
        $this->components->twoColumnDetail('Unparseable values', $unparseable > 0 ? "<fg=yellow>{$unparseable}</>" : '0');

        return self::SUCCESS;
    }

    /**
     * Print every defined custom field with its key, type, and how many items
     * have a value on it — so the operator can pick the right one. A URL/text
     * field with a non-zero count is the obvious "holds a HA reference".
     */
    private function listAvailableFields(): void
    {
        $fields = CustomField::query()
            ->withCount('values')
            ->orderBy('name')
            ->get();

        if ($fields->isEmpty()) {
            $this->warn('  (no custom fields defined yet)');

            return;
        }

        foreach ($fields as $field) {
            $count = (int) $field->values_count;
            $countLabel = $count === 0 ? '0 items' : "{$count} items";
            $this->components->twoColumnDetail(
                "<fg=cyan>{$field->name}</> <fg=gray>(key: {$field->key}, type: {$field->type->value})</>",
                "<fg=gray>{$countLabel}</>",
            );
        }
    }

    /**
     * Turn a custom-field value into Home Assistant link attributes, or null
     * when it carries no usable device/entity reference.
     *
     * @return array{ha_entity_id: string|null, ha_device_id: string|null, friendly_name: null, url: string|null, instance_id: null}|null
     */
    private function parseLink(string $raw): ?array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        // Device-page URL → device id (32-char hex) + the URL itself. Use ~ as
        // the delimiter so the literal # in the character class isn't read as one.
        if (preg_match('~/devices/device/([^/?#\s]+)~i', $raw, $m)) {
            return [
                'ha_entity_id' => null,
                'ha_device_id' => $m[1],
                'friendly_name' => null,
                'url' => $raw,
                'instance_id' => null,
            ];
        }

        // Bare entity id like `sensor.living_room_tv` (domain.object_id).
        if (preg_match('/^[a-z_]+\.[a-z0-9_]+$/i', $raw)) {
            return [
                'ha_entity_id' => $raw,
                'ha_device_id' => null,
                'friendly_name' => null,
                'url' => null,
                'instance_id' => null,
            ];
        }

        return null;
    }
}
