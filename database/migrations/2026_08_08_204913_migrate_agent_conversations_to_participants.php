<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * laravel/ai 0.10 replaced the `user_id` owner on its conversation tables with
 * a polymorphic participant (`participant_type` + `participant_id`), and added
 * an `approval_state` column to messages. The SDK ships this as its own create
 * migration, but ours was published back in 0.7, so the existing tables have to
 * be reshaped in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table($this->conversationsTable(), function (Blueprint $table) {
            $table->string('participant_type')->nullable()->after('id');
            $table->unsignedBigInteger('participant_id')->nullable()->after('participant_type');
        });

        Schema::table($this->messagesTable(), function (Blueprint $table) {
            $table->string('participant_type')->nullable()->after('conversation_id');
            $table->unsignedBigInteger('participant_id')->nullable()->after('participant_type');
            $table->text('approval_state')->nullable()->after('meta');
        });

        $this->backfillParticipants();

        Schema::table($this->conversationsTable(), function (Blueprint $table) {
            $table->dropIndex(['user_id', 'updated_at']);
            $table->dropColumn('user_id');

            $table->index(['participant_type', 'participant_id', 'updated_at'], 'participant_updated_at_index');
        });

        Schema::table($this->messagesTable(), function (Blueprint $table) {
            $table->dropIndex('conversation_index');
            $table->dropIndex(['user_id']);
            $table->dropColumn('user_id');

            $table->index(['conversation_id', 'participant_type', 'participant_id', 'updated_at'], 'conversation_index');
            $table->index(['participant_type', 'participant_id'], 'participant_index');
        });
    }

    public function down(): void
    {
        Schema::table($this->conversationsTable(), function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id');
        });

        Schema::table($this->messagesTable(), function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('conversation_id');
        });

        foreach ([$this->conversationsTable(), $this->messagesTable()] as $table) {
            DB::table($table)
                ->where('participant_type', User::class)
                ->update(['user_id' => DB::raw('participant_id')]);
        }

        Schema::table($this->conversationsTable(), function (Blueprint $table) {
            $table->dropIndex('participant_updated_at_index');
            $table->dropColumn(['participant_type', 'participant_id']);

            $table->index(['user_id', 'updated_at']);
        });

        Schema::table($this->messagesTable(), function (Blueprint $table) {
            $table->dropIndex('conversation_index');
            $table->dropIndex('participant_index');
            $table->dropColumn(['participant_type', 'participant_id', 'approval_state']);

            $table->index(['conversation_id', 'user_id', 'updated_at'], 'conversation_index');
            $table->index(['user_id']);
        });
    }

    /**
     * Point every existing row at the user that owned it. Conversations are
     * only ever held by users here, so the discriminator is a constant.
     */
    private function backfillParticipants(): void
    {
        foreach ([$this->conversationsTable(), $this->messagesTable()] as $table) {
            DB::table($table)
                ->whereNotNull('user_id')
                ->update([
                    'participant_type' => User::class,
                    'participant_id' => DB::raw('user_id'),
                ]);
        }
    }

    private function conversationsTable(): string
    {
        return config('ai.conversations.tables.conversations', 'agent_conversations');
    }

    private function messagesTable(): string
    {
        return config('ai.conversations.tables.messages', 'agent_conversation_messages');
    }
};
