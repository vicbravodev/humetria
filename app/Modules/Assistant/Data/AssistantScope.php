<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Data;

/**
 * Who Humi works for in one request: the company, the person's participant there and what their role may do. Built on
 * the server from the signed-in session and handed to the agent and to each tool by constructor; nothing in it comes
 * from the browser, and the model never chooses it.
 */
final readonly class AssistantScope
{
    /**
     * @param  list<string>  $abilities  the permissions of the person in the company that matter to Humi
     */
    public function __construct(
        public string $tenantId,
        public int $participantId,
        public int $userId,
        public array $abilities,
    ) {}

    public function canSee(string $ability): bool
    {
        return in_array($ability, $this->abilities, true);
    }
}
