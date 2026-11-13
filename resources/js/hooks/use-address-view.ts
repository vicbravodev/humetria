import { useEffect, useState } from 'react';
import {
    CITY_ZOOM,
    STREET_ZOOM,
    cityOf,
    findPlaces,
} from '@/lib/attendance/zone-picker';
import type { Point } from '@/lib/attendance/zone-picker';

/** Where the map opens and how close: the street of an address, or its city when the street is not found. */
export type MapView = Point & { zoom: number };

/** The first place of an address, or null when nothing is found or the search is down. */
async function firstPlace(
    url: string,
    address: string,
    signal: AbortSignal,
): Promise<Point | null> {
    try {
        const [first] = await findPlaces(url, address, signal);

        return first === undefined
            ? null
            : { lat: first.latitude, lng: first.longitude };
    } catch {
        return null;
    }
}

/**
 * Where an address is, searched once when the map opens (the center's domicile): its street; when that is not found, its
 * city (municipality and state), so the map never opens on the whole country. Null while it is searched, when there is
 * nothing to search or when nothing is found. It never says so.
 */
export function useAddressView(
    url: string,
    address: string | null,
): MapView | null {
    const [view, setView] = useState<MapView | null>(null);

    useEffect(() => {
        if (address === null) {
            return;
        }

        const controller = new AbortController();
        const city = cityOf(address);

        void (async () => {
            const street = await firstPlace(url, address, controller.signal);
            const found =
                street ??
                (city === null
                    ? null
                    : await firstPlace(url, city, controller.signal));

            if (found !== null && !controller.signal.aborted) {
                setView({
                    ...found,
                    zoom: street === null ? CITY_ZOOM : STREET_ZOOM,
                });
            }
        })();

        return () => controller.abort();
    }, [url, address]);

    return view;
}
