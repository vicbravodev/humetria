<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Assistant\Contracts\TellsLiveFacts;
use App\Modules\Assistant\Data\AssistantScope;
use Illuminate\Contracts\Cache\Repository;

/**
 * The live facts of a company, from the tools Humi has there (so a module it lacks tells nothing), kept a minute by
 * company and by what the person may see (a tool tells nothing of an area the person does not see): opening the panel on
 * every page stays cheap. No tool tells a fact that depends on who the person is.
 */
final readonly class LiveFacts
{
    private const int SECONDS = 60; // @pest-mutate-ignore: DecrementInteger,IncrementInteger

    public function __construct(private ToolRegistry $tools, private Repository $cache) {}

    /**
     * @return array<string, string> by fact key
     */
    public function for(AssistantScope $scope): array
    {
        return $this->cache->remember("assistant.live_facts.{$scope->tenantId}.".hash('xxh3', implode(',', $scope->abilities)), self::SECONDS, function () use ($scope): array {
            $facts = [];

            foreach ($this->tools->for($scope) as $tool) {
                $facts = [...$facts, ...($tool instanceof TellsLiveFacts ? $tool->facts() : [])];
            }

            return $facts;
        });
    }
}
