<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Assistant\Data\AssistantScope;
use App\Modules\Tenancy\Contracts\CompanyModules;
use Illuminate\Contracts\Container\Container;
use Laravel\Ai\Contracts\Tool;
use LogicException;

/**
 * The tools Humi may use, built for one scope. A new module (violencia laboral, Ley Silla…) adds its tool by listing its
 * class where this is built (AssistantServiceProvider): the scope reaches it by constructor, never from the model.
 */
final readonly class ToolRegistry
{
    /**
     * @param  list<class-string<Tool>>  $tools
     * @param  array<class-string<Tool>, list<string>>  $modules  the modules a tool belongs to: it is offered while the company has any of them. A tool not listed belongs to none, so every company has it.
     */
    public function __construct(private Container $container, private array $tools, private array $modules = []) {}

    /**
     * @return list<Tool>
     */
    public function for(AssistantScope $scope): array
    {
        $companyModules = $this->container->make(CompanyModules::class);
        $offered = array_filter($this->tools, fn (string $class): bool => ($this->modules[$class] ?? []) === []
            || array_any($this->modules[$class], fn (string $module): bool => $companyModules->enabled($scope->tenantId, $module)));

        return array_map(function (string $class) use ($scope): Tool {
            $tool = $this->container->make($class, ['scope' => $scope]);

            return $tool instanceof Tool ? $tool : throw new LogicException("{$class} is not a tool of the SDK.");
        }, array_values($offered));
    }
}
