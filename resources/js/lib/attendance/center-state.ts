import type { CenterMarkMode, OverviewCenter } from '@/types';

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
 * for the QR on site, a screen that already shows the code. The zone comes first: without it nobody marks. A center in
 * the field needs neither.
 */
export function centerState(
    center: Pick<OverviewCenter, 'zones' | 'screens'> & {
        mode?: CenterMarkMode;
    },
    requiresQr: boolean,
): CenterState {
    if (center.mode === 'field') {
        return {
            ready: true,
            label: 'En campo',
            variant: 'success',
            problem: null,
            action: null,
        };
    }

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

/** The policy page's line on the centers that mark in the field instead of by it; the rest of the policy applies to them too. */
export function fieldCentersLine(count: number): string {
    if (count === 0) {
        return 'Todos tus centros marcan igual que la empresa.';
    }

    return count === 1
        ? '1 centro marca en campo: ahí no se piden zonas ni QR, y lo demás de esta política le aplica igual.'
        : `${count} centros marcan en campo: ahí no se piden zonas ni QR, y lo demás de esta política les aplica igual.`;
}
