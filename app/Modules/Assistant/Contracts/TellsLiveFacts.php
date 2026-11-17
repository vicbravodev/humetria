<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Contracts;

/**
 * A tool of Humi that can also say, in one short line each, what is true now in the company: the panel shows it under a
 * first question («3 de 8 cuestionarios contestados») and the closed bubble uses it to invite (keys `invite.{page}`).
 * Counts and states only, read through the same contracts the tool answers from; a fact it cannot tell is left out.
 */
interface TellsLiveFacts
{
    /**
     * @return array<string, string> by fact key
     */
    public function facts(): array;
}
