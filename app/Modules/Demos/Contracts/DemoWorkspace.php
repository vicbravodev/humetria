<?php

declare(strict_types=1);

namespace App\Modules\Demos\Contracts;

/**
 * The demo company the console shows prospects: Manufacturas Alba del Bajío, with a story in every module. It is built
 * outside the modules (database/seeders/Demo), through each module's real actions.
 */
interface DemoWorkspace
{
    /** Tells the whole story into the demo company, creating the company when there is none. */
    public function prepare(): void;

    /** Wipes everything of the demo company, live visitors' traces included, and tells the story again. */
    public function reset(): void;
}
