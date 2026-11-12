import { plural } from '@/lib/plural';
import type { MeasureRow } from '@/types';

/** R7 on the plan: met, missing the measure of some assessments, or with measures past their date. */
export type MeasuresRule = {
    state: 'met' | 'warning' | 'missing' | 'pending';
    count: number;
};

export type MeasureColumn = {
    status: 'planned' | 'in_progress' | 'done';
    title: string;
    measures: MeasureRow[];
};

const COLUMNS: Omit<MeasureColumn, 'measures'>[] = [
    { status: 'planned', title: 'Planeada' },
    { status: 'in_progress', title: 'En curso' },
    { status: 'done', title: 'Hecha' },
];

/** The measures of the page by where they stand (board LS-Plan); a cancelled one stays in the history, only counted. */
export function measureColumns(measures: MeasureRow[]): {
    columns: MeasureColumn[];
    cancelled: number;
} {
    return {
        columns: COLUMNS.map((column) => ({
            ...column,
            measures: measures.filter(
                (measure) => measure.status === column.status,
            ),
        })),
        cancelled: measures.filter((measure) => measure.status === 'cancelled')
            .length,
    };
}

/** The line over the measures: «R7 cumple» or what it lacks. */
export function ruleLine(rule: MeasuresRule): { met: boolean; text: string } {
    if (rule.state === 'met') {
        return {
            met: true,
            text: 'cada evaluación de nivel medio o alto tiene una medida.',
        };
    }

    if (rule.state === 'warning') {
        return {
            met: false,
            text: `${rule.count} ${plural(rule.count, 'medida pasó su fecha compromiso', 'medidas pasaron su fecha compromiso')}.`,
        };
    }

    if (rule.state === 'pending') {
        return { met: false, text: 'aún no hay evaluaciones firmadas.' };
    }

    return {
        met: false,
        text: `${rule.count} ${plural(rule.count, 'evaluación de nivel medio o alto no tiene medida', 'evaluaciones de nivel medio o alto no tienen medida')}.`,
    };
}
