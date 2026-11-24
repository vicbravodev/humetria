import { useEffect, useState } from 'react';

/**
 * Whether the person already allowed the page to read their position, as the browser says it; `unknown` where the browser
 * does not tell (it only asks when the position is asked for). The camera has no entry here: not every browser can be asked.
 */
export function useLocationPermission(): PermissionState | 'unknown' {
    const [state, setState] = useState<PermissionState | 'unknown'>('unknown');

    useEffect(() => {
        if (!('permissions' in navigator)) {
            return undefined;
        }

        let alive = true;
        let status: PermissionStatus | null = null;
        const read = () => {
            if (alive && status !== null) {
                setState(status.state);
            }
        };

        navigator.permissions
            .query({ name: 'geolocation' })
            .then((found) => {
                status = found;
                found.addEventListener('change', read);
                read();
            })
            .catch(() => undefined);

        return () => {
            alive = false;
            status?.removeEventListener('change', read);
        };
    }, []);

    return state;
}
