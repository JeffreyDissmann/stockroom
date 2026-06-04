<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HomeAssistantLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 1:1 back-link from a Stockroom item to a Home Assistant entity.
 *
 * Mirrors PaperlessLink — the remote (HA) side has no Stockroom model, we
 * only store the back-reference. Unlike Paperless this is constrained to one
 * row per item (a device maps to exactly one item) and the Home Assistant
 * integration owns its lifecycle through the v1 API: HA supplies the full
 * `url` to its device page, so there's no URL composition to do here.
 */
class HomeAssistantLink extends Model
{
    /** @use HasFactory<HomeAssistantLinkFactory> */
    use HasFactory;

    protected $fillable = [
        'item_id',
        'ha_entity_id',
        'ha_device_id',
        'friendly_name',
        'url',
        'instance_id',
    ];

    protected $casts = [
        'item_id' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
