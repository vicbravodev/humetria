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

/**
 * What revoking a QR screen does (board A8): the code stops at once, and when it is the only one showing it nobody can
 * scan on site until another is active. `active` is how many screens of the center show the code.
 */
export function revokeScreenText(
    state: KioskScreen['state'],
    active: number,
): string {
    const only =
        state === 'active' && active <= 1
            ? ' Es la única pantalla activa de este centro: nadie podrá marcar con el QR en el sitio hasta que active otra.'
            : '';

    return `Dejará de mostrar el código de inmediato.${only} Para volver a usar la tableta, emite otra pantalla.`;
}
