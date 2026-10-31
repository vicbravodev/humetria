<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Where an IP address is, roughly: "Monterrey, Nuevo León". Behind a contract so tests need no database of addresses.
 */
interface Geolocation
{
    /** The city and state of the address, or null when it is unknown (a private address, or no database yet). */
    public function place(string $ip): ?string;
}
