<?php

declare(strict_types=1);

namespace App\Modules\Demos\Contracts;

/** How a visitor the door let in enters as their demo worker: the way in a person of a real company gets from RH. */
interface DemoEntrance
{
    /**
     * A new one-time address, of the company entered, that opens the session of that person and lets them create their key;
     * the one handed out before stops opening.
     */
    public function for(string $employeeId): string;
}
