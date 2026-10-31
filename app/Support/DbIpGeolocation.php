<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Geolocation;
use MaxMind\Db\Reader;
use Throwable;

/**
 * Looks addresses up in DB-IP's free city database (CC BY 4.0), a file on this server: no address leaves it.
 * `geoip:update` downloads it each month; until then, nothing is found.
 */
final class DbIpGeolocation implements Geolocation
{
    public function place(string $ip): ?string
    {
        $database = config()->string('services.geoip.database');

        if (! is_file($database)) {
            return null;
        }

        try {
            $reader = new Reader($database);
            $record = $reader->get($ip);
            $reader->close();
        } catch (Throwable) {
            // A malformed address or a broken file: the email goes without the place.
            return null;
        }

        $city = self::name($record, 'city');
        $state = is_array($record) && is_array($record['subdivisions'] ?? null) ? self::name($record['subdivisions'], 0) : null;

        return $city === null ? null : implode(', ', array_filter([$city, $state]));
    }

    /** The Spanish name of `$key` in a record, or the English one. */
    private static function name(mixed $record, string|int $key): ?string
    {
        $names = is_array($record) && is_array($record[$key] ?? null) && is_array($record[$key]['names'] ?? null) ? $record[$key]['names'] : [];
        $name = $names['es'] ?? $names['en'] ?? null;

        return is_string($name) ? $name : null;
    }
}
