<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Agents;

use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Assistant\Data\PageContext;
use App\Modules\Assistant\Support\ToolRegistry;
use App\Modules\Assistant\Support\UntrustedText;
use Laravel\Ai\Attributes\MaxSteps;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\RemembersConversations as RemembersConversationsContract;
use Laravel\Ai\Promptable;

/**
 * Humi, the assistant of the HR panel. It works for one scope (a person in one company), with the tools built for it; the
 * SDK keeps the conversation (RemembersConversations: messages() comes from there). It only reads and explains: it
 * changes nothing. Its provider and model come from config.
 */
#[MaxSteps(6)]
#[MaxTokens(1500)]
#[Timeout(60)]
final class HumiAssistant implements Agent, HasTools, RemembersConversationsContract
{
    use Promptable, RemembersConversations;

    public function __construct(
        private readonly AssistantScope $scope,
        private readonly ToolRegistry $tools,
        private readonly ?PageContext $page = null,
    ) {}

    public function provider(): string
    {
        return config()->string('humi.provider');
    }

    public function model(): string
    {
        return config()->string('humi.model');
    }

    public function instructions(): string
    {
        $instructions = (string) file_get_contents(resource_path('ai/humi.system.md'));

        if ($this->page === null) {
            return $instructions;
        }

        // The page is data: its own block, after the instructions, with the name on one line and without markup.
        $screen = json_encode(['tipo' => $this->page->kind, 'id' => $this->page->id, 'nombre' => UntrustedText::line($this->page->label)], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        return "{$instructions}\n\n<contexto_pantalla>\n{$screen}\n</contexto_pantalla>";
    }

    public function tools(): iterable
    {
        return $this->tools->for($this->scope);
    }
}
