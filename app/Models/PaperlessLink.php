<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-item record of a Paperless-ngx document attachment (#7).
 *
 * Lives in its own table so we don't grow the items table with a JSON
 * column of ids — and so the AI-extraction job can insert/delete one
 * link at a time idempotently (`firstOrCreate` on the pair).
 *
 * The remote side has no Stockroom model: `paperless_document_id` is
 * the Paperless doc's primary key; we never store its content or
 * metadata, only the back-reference. The doc itself is accessible via
 * Paperless's URL at click-through time.
 */
class PaperlessLink extends Model
{
    protected $fillable = [
        'item_id',
        'paperless_document_id',
    ];

    protected $casts = [
        'item_id' => 'integer',
        'paperless_document_id' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * URL to view this document in Paperless. Composed from
     * config('paperless.url'); returns null when the integration is
     * disabled (URL empty) so callers can hide the link instead of
     * rendering a broken stub.
     */
    public function paperlessUrl(): ?string
    {
        $base = (string) (config('paperless.url') ?? '');
        if ($base === '') {
            return null;
        }

        return rtrim($base, '/')."/documents/{$this->paperless_document_id}/";
    }

    /**
     * Stockroom-side backlink for a Paperless document id: points at the
     * search page filtered to the items currently linked to that doc.
     * This is the URL we write back into Paperless's `Stockroom URL`
     * custom field, and the one the search filter chip clears.
     *
     * Centralised here so the intake job, the relink-all repair job and
     * the search controller all agree on the shape — change the query
     * key and you change every consumer at once.
     */
    public static function stockroomBacklinkFor(int $documentId): string
    {
        return rtrim((string) config('app.url'), '/').'/search?paperless_document='.$documentId;
    }
}
