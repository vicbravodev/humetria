import type {
    CampaignSummary,
    CentersBySize,
    WorkCenterEvaluation,
} from '@/types';

type Guide = WorkCenterEvaluation['guides'][number];

/** Each size the norm sets apart (num. 2) and the guides it calls for, from the smallest. */
export const SIZES = [
    {
        key: 'small',
        range: 'Hasta 15',
        people: 'Hasta 15 personas',
        guides: 'Guía I',
    },
    {
        key: 'medium',
        range: 'De 16 a 50',
        people: 'De 16 a 50',
        guides: 'Guías I y II',
    },
    {
        key: 'large',
        range: 'Más de 50',
        people: 'Más de 50',
        guides: 'Guías I y III',
    },
] as const satisfies readonly {
    key: keyof CentersBySize;
    range: string;
    people: string;
    guides: string;
}[];

export type CenterSize = (typeof SIZES)[number];

/** Guide numerals, or guide titles ("Guía I"), as the cards write them: "I y III". */
export function numeralsText(guides: string[]): string {
    const numerals = guides.map((guide) => guide.replace('Guía ', ''));

    if (numerals.length <= 1) {
        return numerals[0] ?? '—';
    }

    return `${numerals.slice(0, -1).join(', ')} y ${numerals.at(-1) ?? ''}`;
}

/** The size a work center falls in, by the guides it calls for; null with nobody yet. */
export function centerSize(guides: Guide[]): CenterSize | null {
    const last = guides.at(-1);

    if (last === undefined) {
        return null;
    }

    return SIZES[{ I: 0, II: 1, III: 2 }[last]] ?? null;
}

/** Why those guides apply: "Con más de 50 personas aplican las Guías I y III." */
export function guidesReason(guides: Guide[]): string {
    switch (centerSize(guides)?.key) {
        case 'small':
            return 'Con hasta 15 personas aplica solo la Guía I.';
        case 'medium':
            return 'De 16 a 50 personas aplican las Guías I y II.';
        case 'large':
            return 'Con más de 50 personas aplican las Guías I y III.';
        default:
            return 'Sin personal todavía: ninguna guía aplica.';
    }
}

export type CenterStatus = {
    label: string;
    variant: 'default' | 'secondary' | 'success' | 'warning' | 'destructive';
};

/** Where a work center stands with NOM-035: running, overdue, up to date, in draft, or never evaluated. */
export function centerStatus(center: WorkCenterEvaluation): CenterStatus {
    if (center.openCampaign?.status === 'active') {
        return { label: 'En curso', variant: 'default' };
    }

    if (center.overdue) {
        return { label: 'Vencida', variant: 'destructive' };
    }

    if (center.lastEvaluatedOn !== null) {
        return { label: 'Al día', variant: 'success' };
    }

    if (center.openCampaign !== null) {
        return { label: 'Borrador', variant: 'secondary' };
    }

    return center.workers === 0
        ? { label: 'Sin personal', variant: 'secondary' }
        : { label: 'Sin evaluar', variant: 'warning' };
}

/** The line under the last evaluation: when the next one is due (num. 7.9), and whether a draft waits. */
export function centerFollowUp(center: WorkCenterEvaluation): string {
    const draft = center.openCampaign !== null;

    if (center.dueOn === null) {
        return draft
            ? 'Borrador sin lanzar'
            : 'La norma pide evaluar al menos cada dos años';
    }

    const due = center.overdue
        ? `Venció el ${center.dueOn}`
        : `Siguiente antes del ${center.dueOn}`;

    return draft ? `${due} · borrador nuevo sin lanzar` : due;
}

/** A running evaluation: "Cierra el 10 nov 2026 · faltan 42". */
export function openCampaignLine(campaign: CampaignSummary): string {
    const closes = `Cierra el ${campaign.closesOn ?? ''}`;

    if (campaign.progress === null) {
        return closes;
    }

    const left = campaign.progress.target - campaign.progress.completed;

    return left > 0 ? `${closes} · faltan ${left}` : `${closes} · completa`;
}

/** From launch to close ("10 oct 2026", "29 oct 2026"), without repeating the month or the year: "10 al 29 oct 2026". */
export function periodText(from: string | null, to: string | null): string {
    if (from === null || to === null) {
        return from === null ? `Hasta el ${to ?? ''}` : `Desde el ${from}`;
    }

    const [fromDay, fromMonth, fromYear] = from.split(' ');
    const [, toMonth, toYear] = to.split(' ');

    if (fromYear !== toYear) {
        return `${from} al ${to}`;
    }

    return fromMonth === toMonth
        ? `${fromDay} al ${to}`
        : `${fromDay} ${fromMonth} al ${to}`;
}
