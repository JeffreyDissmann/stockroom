<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProposalField;
use App\Enums\ProposalStatus;
use Database\Factories\ItemProposalFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A change the review agent suggests for an item, awaiting a human decision.
 *
 * Nothing here is applied automatically. The agent writes proposals; an admin
 * accepts or rejects them; only an accept touches the item.
 */
class ItemProposal extends Model
{
    /** @use HasFactory<ItemProposalFactory> */
    use HasFactory;

    protected $fillable = [
        'item_id',
        'field',
        'current_value',
        'proposed_value',
        'status',
        'source_image_ids',
        'model',
        'reviewed_at',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            'field' => ProposalField::class,
            'status' => ProposalStatus::class,
            'source_image_ids' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /** The admin who accepted or rejected it; null while pending. */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @param Builder<$this> $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ProposalStatus::Pending);
    }

    /**
     * Whether the item still holds the value this was proposed against.
     *
     * A proposal can sit in the queue for days while someone edits the item by
     * hand. Accepting a stale one would silently undo that edit, so the review
     * path checks this first.
     */
    public function isStale(): bool
    {
        return (string) ($this->item->{$this->field->value} ?? '') !== (string) ($this->current_value ?? '');
    }
}
