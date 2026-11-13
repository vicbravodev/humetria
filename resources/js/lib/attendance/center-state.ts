import type { OverviewCenter } from '@/types';

/** Where a center stands for marking: what it lacks, how it is called, and what to tell RH about it. */
export type CenterState = {
    ready: boolean;
    label: string;
    variant: 'success' | 'warning' | 'destructive';
    /** One sentence on what stops its people from marking; null when nothing does. */
    problem: string | null;
    /** The shortcut to what it lacks first; null when it lacks nothing. */
    action: string | null;
};

/**
 * Whether the people of a center can mark in it (board A1): they need a zone for their phone and, when the policy asks
 * for the QR on site, a screen that already shows the code. The zone comes first: without it nobody marks.
 */
export function centerState(
    center: Pick<OverviewCenter, 'zones' | 'screens'>,
    requiresQr: boolean,
): CenterState {
    if (center.zones === 0) {
        return {
            ready: false,
            label: 'Sin zonas',
            variant: 'destructive',
            problem:
                'Sin zona, nadie puede marcar con su ubicación en este centro.',
            action: 'Agregar la primera zona',
        };
    }

    if (requiresQr && center.screens === 0) {
        return {
            ready: false,
            label: 'Falta pantalla QR',
            variant: 'warning',
            problem:
                'Tu política pide QR en el centro y la pantalla aún no está activa: hoy nadie puede marcar en sitio.',
            action: 'Activar pantalla QR',
        };
    }

    return {
        ready: true,
        label: 'Listo para marcar',
        variant: 'success',
        problem: null,
        action: null,
    };
}

/** «5 personas · 2 zonas · 0 pantallas activas», each in the singular when it is one. */
export function centerFacts(
    center: Pick<OverviewCenter, 'people' | 'zones' | 'screens'>,
): string {
    const count = (n: number, one: string, many: string) =>
        `${n} ${n === 1 ? one : many}`;

    return [
        count(center.people, 'persona', 'personas'),
        count(center.zones, 'zona', 'zonas'),
        count(center.screens, 'pantalla activa', 'pantallas activas'),
    ].join(' · ');
}

/** What RH reads before sending the links of a center that cannot mark yet (the people can still link their phones). */
export function notReadyNote(centerName: string): string {
    return `Hoy nadie puede marcar en ${centerName}: le falta una zona o una pantalla.`;
}
