import { useState } from 'react';
import { coordinate } from '@/lib/attendance/zone';
import type { Point } from '@/lib/attendance/zone-picker';
import { tooFarToPlace } from '@/lib/attendance/zone-picker';
import type { AttendanceZone } from '@/types';

const text = (value: number | undefined): string =>
    value === undefined ? '' : coordinate(value);

/**
 * The center and radius of the zone RH is writing, as the fields hold them. A center touched on a map zoomed far out
 * waits for a confirmation before the zone can be saved; typing the coordinates, searching or taking the location is
 * precise and needs none.
 */
export function useZoneDraft(zone: AttendanceZone | null) {
    const [latitude, setLatitude] = useState(text(zone?.latitude));
    const [longitude, setLongitude] = useState(text(zone?.longitude));
    const [radius, setRadius] = useState(String(zone?.radius ?? 100));
    const [far, setFar] = useState(false);
    const [confirmed, setConfirmed] = useState(false);

    return {
        latitude,
        longitude,
        radius,
        setRadius,
        far,
        confirmed,
        setConfirmed,
        blocked: far && !confirmed,
        place: (point: Point, zoom?: number) => {
            setLatitude(coordinate(point.lat));
            setLongitude(coordinate(point.lng));
            setFar(zoom !== undefined && tooFarToPlace(zoom));
            setConfirmed(false);
        },
        typeLatitude: (value: string) => {
            setLatitude(value);
            setFar(false);
        },
        typeLongitude: (value: string) => {
            setLongitude(value);
            setFar(false);
        },
    };
}

export type ZoneDraft = ReturnType<typeof useZoneDraft>;
