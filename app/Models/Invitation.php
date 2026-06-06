<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    /**
     * How long a freshly created invite link stays valid.
     */
    public const LIFETIME_DAYS = 7;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'token',
        'label',
        'email',
        'created_by',
        'accepted_by',
        'expires_at',
        'accepted_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    /**
     * Generate a fresh, unguessable, URL-safe invite token.
     */
    public static function generateToken(): string
    {
        return Str::random(48);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /**
     * An invite is usable only while unaccepted and unexpired.
     */
    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isPast();
    }

    /**
     * Limit to invites that can still be accepted.
     *
     * @param  Builder<Invitation>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('accepted_at')->where('expires_at', '>', Carbon::now());
    }
}
