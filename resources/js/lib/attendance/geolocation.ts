/**
 * The device's position, asked for once at the moment of marking (criterio 3.4): accurate (GPS when there is one), fresh
 * (no cached reading) and with a time limit. Nothing is kept in the browser.
 */

export type Reading = { lat: number; lng: number; accuracy: number };

export type LocationError =
    | 'denied'
    | 'unavailable'
    | 'timeout'
    | 'unsupported';

export type LocationResult =
    | { ok: true; position: Reading }
    | { ok: false; error: LocationError };

const BY_CODE: Partial<Record<number, LocationError>> = {
    1: 'denied',
    2: 'unavailable',
    3: 'timeout',
};

/** Where the device is now, or why it cannot tell. Never rejects. */
export function currentPosition(
    geolocation: Geolocation | undefined,
    timeoutMs = 15000,
): Promise<LocationResult> {
    if (geolocation === undefined) {
        return Promise.resolve({ ok: false, error: 'unsupported' });
    }

    return new Promise((resolve) => {
        try {
            geolocation.getCurrentPosition(
                ({ coords }) =>
                    resolve({
                        ok: true,
                        position: {
                            lat: coords.latitude,
                            lng: coords.longitude,
                            accuracy: coords.accuracy,
                        },
                    }),
                ({ code }) =>
                    resolve({
                        ok: false,
                        error: BY_CODE[code] ?? 'unavailable',
                    }),
                { enableHighAccuracy: true, timeout: timeoutMs, maximumAge: 0 },
            );
        } catch {
            resolve({ ok: false, error: 'unavailable' });
        }
    });
}
