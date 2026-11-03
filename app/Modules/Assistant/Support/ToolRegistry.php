<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use App\Modules\Assistant\Data\AssistantScope;
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
     */
    public function __construct(private Container $container, private array $tools) {}

    /**
     * @return list<Tool>
     */
    public function for(AssistantScope $scope): array
    {
        return array_map(function (string $class) use ($scope): Tool {
            $tool = $this->container->make($class, ['scope' => $scope]);

            return $tool instanceof Tool ? $tool : throw new LogicException("{$class} is not a tool of the SDK.");
        }, $this->tools);
    }
}
