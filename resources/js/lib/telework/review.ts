import type {
    ReviewDecisionValue,
    ReviewEvidence,
    WorkplaceStatusValue,
} from '@/types';

export type BadgeTone =
    | 'default'
    | 'secondary'
    | 'success'
    | 'warning'
    | 'destructive';

/** The badge of a workplace's status: validated is success, a plan needs a look, not validated is blocked. */
export function statusTone(status: WorkplaceStatusValue): BadgeTone {
    switch (status) {
        case 'valid':
            return 'success';
        case 'valid_with_plan':
            return 'warning';
        case 'not_valid':
            return 'destructive';
        case 'retired':
            return 'secondary';
        case 'pending':
            return 'default';
    }
}

export const DECISIONS: {
    value: ReviewDecisionValue;
    label: string;
    hint: string;
}[] = [
    {
        value: 'valid',
        label: 'Validado',
        hint: 'El lugar cumple; vale hasta la siguiente revisión.',
    },
    {
        value: 'valid_with_plan',
        label: 'Validado con plan de acción',
        hint: 'Cumple, con mejoras por hacer en una fecha compromiso.',
    },
    {
        value: 'not_valid',
        label: 'No validado',
        hint: 'No cumple todavía; las notas explican por qué.',
    },
];

/** What a decision still needs besides the choice itself. */
export function decisionNeeds(decision: ReviewDecisionValue): {
    plan: boolean;
    notes: boolean;
} {
    return {
        plan: decision === 'valid_with_plan',
        notes: decision === 'not_valid',
    };
}

/** The answer as the person gave it, in the Comisión's words. */
export function answerLabel(answer: string | null): string {
    switch (answer) {
        case 'yes':
            return 'Sí';
        case 'no':
            return 'No';
        case 'not_applicable':
            return 'No aplica';
        default:
            return 'Sin respuesta';
    }
}

/** The evidence still open, oldest request last as the server sorts it. */
export function openEvidence(evidence: ReviewEvidence[]): ReviewEvidence[] {
    return evidence.filter((request) => !request.fulfilled);
}

export function evidenceKindLabel(kind: ReviewEvidence['kind']): string {
    return kind === 'photos' ? 'Fotos' : 'Comprobación a distancia';
}
