<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;

/**
 * Delete assistant conversations (and their messages) that have been idle
 * longer than `ai.chat_retention_days` — old chats clutter the DB and are no
 * longer rehydrated by the panel after the much shorter reset window anyway.
 * The SDK migration doesn't cascade messages, so we delete them explicitly.
 */
class ForgetOldConversations extends Command
{
    protected $signature = 'ai:forget-conversations {--days= : Override the retention window (in days)}';

    protected $description = 'Delete assistant conversations idle longer than the retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('ai.chat_retention_days', 3));

        if ($days <= 0) {
            $this->info('Retention disabled (days=0); nothing to do.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);
        $ids = Conversation::where('updated_at', '<', $cutoff)->pluck('id');

        if ($ids->isEmpty()) {
            $this->info("No conversations idle longer than {$days} day(s).");

            return self::SUCCESS;
        }

        ConversationMessage::whereIn('conversation_id', $ids)->delete();
        $deleted = Conversation::whereIn('id', $ids)->delete();

        $this->info("Forgot {$deleted} conversation(s) idle longer than {$days} day(s).");

        return self::SUCCESS;
    }
}
