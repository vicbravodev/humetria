<?php

declare(strict_types=1);

namespace App\Modules\FirstSteps\Data;

/**
 * One step of the guided tour. `href` is null when the step runs on the page already open (Humi's tour); `tool` names the
 * evaluation tool the step points at.
 */
final readonly class FirstStep
{
    public function __construct(public string $key, public bool $done, public ?string $href, public ?string $tool = null) {}
}
