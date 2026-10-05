<?php

namespace App\Console\Commands;

use App\Enums\ConversationState;
use App\Models\Conversation;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('conversation:resume-ai')]
#[Description('Resume AI handling for conversations where human owner has been idle for 3+ minutes')]
class ResumeAiForIdleConversations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = now()->subMinutes(3);

        $idleConversations = Conversation::where('handler', 'human')
            ->where('status', '!=', ConversationState::Resolved)
            ->whereNotNull('human_last_replied_at')
            ->where('human_last_replied_at', '<=', $cutoff)
            ->get();

        if ($idleConversations->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($idleConversations as $conversation) {
            $conversation->update([
                'handler' => 'ai',
                'status' => ConversationState::AiHandling,
                'human_last_replied_at' => null,
            ]);

            Log::info("[HumanTakeover] Auto-resumed AI for conversation #{$conversation->id} after 3+ minutes of human inactivity.");
        }

        $this->info("Successfully resumed AI for {$idleConversations->count()} conversation(s).");

        return self::SUCCESS;
    }
}
