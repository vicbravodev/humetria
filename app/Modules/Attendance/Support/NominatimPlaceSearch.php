<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Contracts\PlaceSearch;
use App\Modules\Attendance\Data\Place;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Places by address from OpenStreetMap's Nominatim, asked from the server so the browser talks to no other origin.
 * Its usage policy: an identifying User-Agent, no autocomplete (RH searches on submit) and caching what was asked.
 */
final readonly class NominatimPlaceSearch implements PlaceSearch
{
    private const string URL = 'https://nominatim.openstreetmap.org/search';

    public function find(string $address): array
    {
        $key = 'attendance.places.'.hash('sha256', Str::lower($address));

        return $this->places(Cache::remember($key, now()->addDay(), fn (): mixed => $this->ask($address)));
    }

    private function ask(string $address): mixed
    {
        return Http::withUserAgent('Humetria/1.0 (+'.Config::string('app.url').')')
            ->timeout(5)
            ->get(self::URL, [
                'q' => $address,
                'format' => 'jsonv2',
                'countrycodes' => 'mx',
                'limit' => 5,
                'accept-language' => 'es',
            ])
            ->throw()
            ->json();
    }

    /**
     * The answer is outside data: only the entries with a name and numeric coordinates become places.
     *
     * @return list<Place>
     */
    private function places(mixed $found): array
    {
        $places = [];

        foreach (is_array($found) ? $found : [] as $entry) {
            $name = is_array($entry) ? ($entry['display_name'] ?? null) : null;
            $latitude = is_array($entry) ? ($entry['lat'] ?? null) : null;
            $longitude = is_array($entry) ? ($entry['lon'] ?? null) : null;

            if (is_string($name) && is_numeric($latitude) && is_numeric($longitude)) {
                $places[] = new Place($name, (float) $latitude, (float) $longitude);
            }
        }

        return $places;
    }
}
