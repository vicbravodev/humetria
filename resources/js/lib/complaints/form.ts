import type { Option } from '@/lib/option-search';

/** The steps of the form, in order; the person moves between them and none advances alone. */
export type StepId =
    | 'intro'
    | 'type'
    | 'story'
    | 'whom'
    | 'identity'
    | 'review';

export const STEPS: readonly StepId[] = [
    'intro',
    'type',
    'story',
    'whom',
    'identity',
    'review',
];

export type Draft = {
    type: string;
    category: string;
    description: string;
    occurredOn: string;
    workCenter: Option | null;
    respondentKind: string;
    respondentText: string;
    identityMode: 'anonymous' | 'confidential';
    reporterName: string;
    reporterContact: string;
    involvedText: string;
};

/** The written notice of a severe traumatic event (num. 6.5): the norm asks for the name of who writes it and the day it happened. */
export const NOTICE = 'traumatic_event_notice';

export const emptyDraft: Draft = {
    type: '',
    category: '',
    description: '',
    occurredOn: '',
    workCenter: null,
    respondentKind: '',
    respondentText: '',
    identityMode: 'anonymous',
    reporterName: '',
    reporterContact: '',
    involvedText: '',
};

/** What the server takes: snake_case, as it validates it. */
export type Payload = {
    ticket: string;
    type: string;
    category: string;
    description: string;
    occurred_on: string;
    work_center_id: string;
    respondent_kind: string;
    respondent_text: string;
    identity_mode: string;
    reporter_name: string;
    reporter_contact: string;
    involved_text: string;
    website: string;
};

export function stepAfter(step: StepId): StepId {
    return STEPS[Math.min(STEPS.indexOf(step) + 1, STEPS.length - 1)] ?? step;
}

export function stepBefore(step: StepId): StepId {
    return STEPS[Math.max(STEPS.indexOf(step) - 1, 0)] ?? step;
}

/** What is missing to leave a step, in words for the person; undefined when it can be left. */
export function problemWith(
    step: StepId,
    draft: Draft,
    limits: { min: number; max: number },
): string | undefined {
    if (step === 'type' && draft.type === '') {
        return 'Elija qué quiere informar.';
    }

    if (step === 'story') {
        return storyProblem(draft, limits);
    }

    if (step === 'identity') {
        return identityProblem(draft);
    }

    if (step === 'whom' && draft.respondentKind === '') {
        return 'Elija de quién se trata, o «No sé o prefiero no decirlo».';
    }

    return undefined;
}

function identityProblem(draft: Draft): string | undefined {
    if (draft.type === NOTICE && draft.identityMode === 'anonymous') {
        return 'Este aviso necesita su nombre: la norma lo pide a quien lo escribe.';
    }

    return draft.identityMode === 'confidential' &&
        draft.reporterName.trim() === ''
        ? 'Escriba su nombre.'
        : undefined;
}

function storyProblem(
    draft: Draft,
    limits: { min: number; max: number },
): string | undefined {
    const length = draft.description.trim().length;

    if (draft.type === NOTICE && draft.occurredOn === '') {
        return 'Indique la fecha en que ocurrió.';
    }

    if (length < limits.min) {
        return `Cuéntenos un poco más: se necesitan al menos ${limits.min} caracteres.`;
    }

    return length > limits.max
        ? `Es demasiado largo: el máximo es de ${limits.max} caracteres.`
        : undefined;
}

export function payload(draft: Draft, ticket: string): Payload {
    const named = draft.identityMode === 'confidential';

    return {
        ticket,
        type: draft.type,
        category: draft.category,
        description: draft.description.trim(),
        occurred_on: draft.occurredOn,
        work_center_id: draft.workCenter?.value ?? '',
        respondent_kind: draft.respondentKind,
        respondent_text: draft.respondentText.trim(),
        identity_mode: draft.identityMode,
        reporter_name: named ? draft.reporterName.trim() : '',
        reporter_contact: named ? draft.reporterContact.trim() : '',
        involved_text: draft.type === NOTICE ? draft.involvedText.trim() : '',
        website: '',
    };
}

/** What changes in the draft when the person chooses what to report: the notice of a traumatic event goes with their name. */
export function changesForType(type: string): Partial<Draft> {
    return type === NOTICE
        ? {
              type,
              category: '',
              identityMode: 'confidential',
              respondentKind: 'unknown',
          }
        : { type, category: '' };
}
