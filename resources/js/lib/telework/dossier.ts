import type { WorkplaceStatusValue } from '@/types';

export type DossierBlockKey =
    | 'roster'
    | 'workplaces'
    | 'fixed'
    | 'checklist'
    | 'equipment'
    | 'trainings'
    | 'notices'
    | 'committee'
    | 'document';

/** The rows of table 10.3 whose evidence is a document in force. */
export const DOCUMENT_NUMERALS = [
    '5.2',
    '5.3',
    '5.6',
    '5.8',
    '5.10',
    '5.13',
    '5.14',
];

const BLOCKS: Record<string, DossierBlockKey> = {
    '5.1': 'roster',
    '5.1.1': 'workplaces',
    '5.1.2': 'fixed',
    '5.5': 'workplaces',
    '5.4 y 7': 'checklist',
    '5.5.1': 'checklist',
    '5.7': 'equipment',
    '5.9 y 8': 'trainings',
    '5.11': 'notices',
    '5.12': 'committee',
};

/** Which block of evidence a row of table 10.3 prints. */
export function dossierBlock(numeral: string): DossierBlockKey {
    return BLOCKS[numeral] ?? 'document';
}

/** A workplace that lets the person telework from it today: validated and not lapsed (5.1.2). */
export function isFixedPlace(status: WorkplaceStatusValue): boolean {
    return status === 'valid' || status === 'valid_with_plan';
}

/** What a row that does not apply says in place of its evidence: a neutral sentence, never «Falta». */
export function inapplicableText(numeral: string): string {
    return numeral === '5.11'
        ? 'No aplica: nadie ha enviado un aviso de riesgo de trabajo.'
        : 'No aplica: nadie teletrabaja en este centro todavía.';
}
