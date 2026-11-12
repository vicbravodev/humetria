import { useRef, useState } from 'react';
import { findPlaces } from '@/lib/attendance/zone-picker';
import type { Place } from '@/lib/attendance/zone-picker';

type Status = { busy: boolean; places: Place[] | null; failed: boolean };

/** The address search of a zone's map: on demand (Nominatim asks for no autocomplete), the last search wins. */
export function usePlaceSearch(url: string) {
    const [status, setStatus] = useState<Status>({
        busy: false,
        places: null,
        failed: false,
    });
    const last = useRef<AbortController | null>(null);

    const search = async (address: string) => {
        if (address.trim().length < 3) {
            return;
        }

        last.current?.abort();
        const controller = new AbortController();
        last.current = controller;
        setStatus({ busy: true, places: null, failed: false });

        try {
            const places = await findPlaces(url, address, controller.signal);
            setStatus({ busy: false, places, failed: false });
        } catch {
            if (!controller.signal.aborted) {
                setStatus({ busy: false, places: null, failed: true });
            }
        }
    };

    const clear = () => setStatus({ busy: false, places: null, failed: false });

    return { ...status, search, clear };
}
