import { shortMoment } from '@/lib/attendance/corrections';
import type { KioskScreen } from '@/types';

const BADGES: Record<
    KioskScreen['state'],
    { label: string; variant: 'warning' | 'success' | 'secondary' }
> = {
    issued: { label: 'Emitida, sin activar', variant: 'warning' },
    active: { label: 'Activa', variant: 'success' },
    revoked: { label: 'Revocada', variant: 'secondary' },
};

/** Where a QR screen stands, in a chip. */
export function screenBadge(state: KioskScreen['state']) {
    return BADGES[state];
}

/** When a screen last showed its code, when it was revoked, or that it has not shown one yet. */
export function screenSeen(
    screen: Pick<KioskScreen, 'state' | 'lastSeenAt' | 'revokedAt'>,
): string {
    if (screen.revokedAt !== null) {
        return `Revocada el ${shortMoment(screen.revokedAt)}`;
    }

    return screen.lastSeenAt === null
        ? 'Aún no muestra el código'
        : `Vista el ${shortMoment(screen.lastSeenAt)}`;
}
