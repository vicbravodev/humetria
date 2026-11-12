<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Contracts;

use App\Modules\Attendance\Data\Place;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/** Finds the places that match an address, to center a zone there. The provider is outside: it may be down. */
interface PlaceSearch
{
    /**
     * @return list<Place>
     *
     * @throws ConnectionException|RequestException when the provider does not answer
     */
    public function find(string $address): array;
}
