import type { Range, SectorValue } from '@/lib/applicability';

/** What a center answers in the first-time setup (board V3-Onboarding); null while it has not. */
export type CenterAnswers = {
    hasTeleworkers: boolean | null;
    standing: boolean | null;
    sector: SectorValue | null;
};

export type OnboardingCenter = CenterAnswers & {
    id: string;
    name: string;
    activity: string;
    people: number;
    range: Range;
};

export type Tag = { label: string; tone: 'yes' | 'no' | 'pending' };

/** The NOM-035 guides of each size of center (num. 4.4), with the Guía I for everyone (criterio C-04). */
export const GUIDES: Record<Range, string> = {
    up_to_15: 'Guía I',
    '16_to_50': 'Guías I y II',
    over_50: 'Guías I y III',
};

/** NOM-035 applies to every center. */
export const APPLIES: Tag = { label: 'Aplica', tone: 'yes' };

const PENDING: Tag = { label: 'Falta contestar', tone: 'pending' };
const NO: Tag = { label: 'No aplica', tone: 'no' };

export function teleworkTag({ hasTeleworkers }: CenterAnswers): Tag {
    if (hasTeleworkers === null) {
        return PENDING;
    }

    return hasTeleworkers ? APPLIES : NO;
}

/** Ley Silla reaches an industrial center only where the work allows a seat (criterio C-03). */
export function seatingTag({ standing, sector }: CenterAnswers): Tag {
    if (standing === null) {
        return PENDING;
    }

    if (!standing) {
        return NO;
    }

    if (sector === null) {
        return { label: 'Falta el sector', tone: 'pending' };
    }

    return {
        label: sector === 'industrial' ? 'En algunos puestos' : 'Aplica',
        tone: 'yes',
    };
}

/** Both questions answered, and the sector wherever someone works standing. */
export function answered(center: CenterAnswers): boolean {
    return (
        center.hasTeleworkers !== null &&
        center.standing !== null &&
        (!center.standing || center.sector !== null)
    );
}

/** The button of the setup: how many centers to answer while any is left, then how many routes it creates (NOM-035 in every center). */
export function createLabel(centers: CenterAnswers[]): string {
    if (!centers.every((center) => answered(center))) {
        return centers.length === 1
            ? 'Contesta el centro'
            : `Contesta los ${centers.length} centros`;
    }

    const routes = centers.reduce(
        (count, center) =>
            count +
            1 +
            (center.hasTeleworkers ? 1 : 0) +
            (center.standing ? 1 : 0),
        0,
    );

    return routes === 1 ? 'Crear mi ruta' : `Crear mis ${routes} rutas`;
}
