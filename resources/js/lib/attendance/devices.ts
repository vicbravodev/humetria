import { shortMoment } from '@/lib/attendance/corrections';
import type { DevicePerson, LinkedDevice } from '@/types';

export type DevicesFilters = {
    q: string;
    status: string;
    /** The center the list narrows to; empty for every center. */
    center: string;
};

/** The devices page's address as the server reads it: only what differs from the default view. */
export function devicesQuery(
    filters: DevicesFilters,
): Partial<Record<keyof DevicesFilters, string>> {
    const q = filters.q.trim();

    return {
        ...(q === '' ? {} : { q }),
        ...(filters.status === 'all' ? {} : { status: filters.status }),
        ...(filters.center === '' ? {} : { center: filters.center }),
    };
}

/**
 * What kind of key a device holds, said plainly: a synced passkey lives in the person's passkey account, so it may mark
 * from another device of that account; one that cannot be backed up stays on this device.
 */
export function deviceKind(synced: boolean): { label: string; hint: string } {
    return synced
        ? {
              label: 'Llave sincronizada',
              hint: 'Vive en la cuenta de llaves de acceso de la persona (iCloud, Google): puede usarse desde otro equipo de esa cuenta, no solo desde este teléfono.',
          }
        : {
              label: 'Solo este dispositivo',
              hint: 'La llave no sale de este equipo: para marcar desde otro, la persona necesita un enlace nuevo.',
          };
}

/** When a device was linked, and when it last marked or was revoked. */
export function deviceUse(
    device: Pick<LinkedDevice, 'enrolledAt' | 'lastUsedAt' | 'revokedAt'>,
): string {
    const linked = `Vinculado el ${shortMoment(device.enrolledAt)}`;

    if (device.revokedAt !== null) {
        return `${linked} · revocado el ${shortMoment(device.revokedAt)}`;
    }

    return device.lastUsedAt === null
        ? `${linked} · aún sin marcar`
        : `${linked} · usado el ${shortMoment(device.lastUsedAt)}`;
}

const STATUSES: Record<
    DevicePerson['status'],
    { label: string; variant: 'success' | 'warning' | 'secondary' }
> = {
    linked: { label: 'Con dispositivo', variant: 'success' },
    unlinked: { label: 'Sin dispositivo', variant: 'warning' },
    revoked: { label: 'Revocado', variant: 'secondary' },
};

/** Where a person stands with their devices, in a chip. */
export function deviceStatus(status: DevicePerson['status']) {
    return STATUSES[status];
}
