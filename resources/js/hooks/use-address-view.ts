import { useEffect, useState } from 'react';
import { findPlaces } from '@/lib/attendance/zone-picker';
import type { Point } from '@/lib/attendance/zone-picker';

/**
 * Where an address is, searched once when the map opens (the center's domicile): the first place found, or null while it
 * is searched, when there is nothing to search, when nothing is found or when the search is down. It never says so.
 */
export function useAddressView(
    url: string,
    address: string | null,
): Point | null {
    const [view, setView] = useState<Point | null>(null);

    useEffect(() => {
        if (address === null) {
            return;
        }

        const controller = new AbortController();
        findPlaces(url, address, controller.signal)
            .then(([first]) => {
                if (first !== undefined) {
                    setView({ lat: first.latitude, lng: first.longitude });
                }
            })
            .catch(() => undefined);

        return () => controller.abort();
    }, [url, address]);

    return view;
}
