<?php

declare(strict_types=1);

namespace Database\Seeders\Demo;

/** One module's part of the demo's story, told through that module's real actions inside the demo company. */
interface DemoStory
{
    public function tell(DemoContext $context): void;
}
