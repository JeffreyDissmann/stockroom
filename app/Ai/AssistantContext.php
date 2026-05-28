<?php

declare(strict_types=1);

namespace App\Ai;

/**
 * Request-scoped state shared between the assistant controller and its tools.
 * The controller records which conversation a prompt belongs to so a write tool
 * (e.g. CreateItem) can locate a pending uploaded image stashed for that thread.
 */
class AssistantContext
{
    public ?string $conversationId = null;
}
