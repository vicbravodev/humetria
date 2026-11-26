<?php

declare(strict_types=1);

namespace App\Modules\Demos\Contracts;

use Closure;

/**
 * The demo company the console shows prospects: Manufacturas Alba del Bajío, with a story in every module. It is built
 * outside the modules (database/seeders/Demo), through each module's real actions.
 */
interface DemoWorkspace
{
    /**
     * Tells the whole story into the demo company: it creates the company the first time and, after that, resets it first,
     * so preparing twice leaves the same company with the same story.
     *
     * @param  (Closure(string): void)|null  $step  told each step's label as it starts, for the console's progress
     */
    public function prepare(?Closure $step = null): void;

    /**
     * Stages a guided session in the demo company, after prepare(): a work center of its own («Sala · {client}», or «Sala
     * de demostración» with no client), the group minimum at 1 and WhatsApp on with the words in force.
     *
     * @return string the id of the session's work center
     */
    public function stageSession(?string $clientName): string;

    /**
     * Wipes everything of the demo company, live visitors' traces and files included, except its audit trail. The company
     * row (its slug, its complaint code) and its members' accounts stay; nothing happens when there is no demo.
     */
    public function reset(): void;
}
