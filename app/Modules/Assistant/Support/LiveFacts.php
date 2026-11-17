<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Assistant\Contracts\TellsLiveFacts;
use App\Modules\Assistant\Data\AssistantScope;
use Illuminate\Contracts\Cache\Repository;

/**
 * The live facts of a company, from the tools Humi has there (so a module it lacks tells nothing), kept a minute by
 * company: opening the panel on every page stays cheap. No tool tells a fact that depends on the person.
 */
final readonly class LiveFacts
{
    private const int SECONDS = 60;

    public function __construct(private ToolRegistry $tools, private Repository $cache) {}

    /**
     * @return array<string, string> by fact key
     */
    public function for(AssistantScope $scope): array
    {
        return $this->cache->remember("assistant.live_facts.{$scope->tenantId}", self::SECONDS, function () use ($scope): array {
            $facts = [];

            foreach ($this->tools->for($scope) as $tool) {
                $facts = [...$facts, ...($tool instanceof TellsLiveFacts ? $tool->facts() : [])];
            }

            return $facts;
        });
    }
}
