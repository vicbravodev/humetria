import type { Option } from '@/lib/option-search';

/** The steps of the form, in order; the person moves between them and none advances alone. */
export type StepId = 'intro' | 'type' | 'story' | 'whom' | 'review';

export const STEPS: readonly StepId[] = [
    'intro',
    'type',
    'story',
    'whom',
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
};

export const emptyDraft: Draft = {
    type: '',
    category: '',
    description: '',
    occurredOn: '',
    workCenter: null,
    respondentKind: '',
    respondentText: '',
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
        return storyProblem(draft.description.trim().length, limits);
    }

    if (step === 'whom' && draft.respondentKind === '') {
        return 'Elija de quién se trata, o «No sé o prefiero no decirlo».';
    }

    return undefined;
}

function storyProblem(
    length: number,
    limits: { min: number; max: number },
): string | undefined {
    if (length < limits.min) {
        return `Cuéntenos un poco más: se necesitan al menos ${limits.min} caracteres.`;
    }

    return length > limits.max
        ? `Es demasiado largo: el máximo es de ${limits.max} caracteres.`
        : undefined;
}

export function payload(draft: Draft, ticket: string): Payload {
    return {
        ticket,
        type: draft.type,
        category: draft.category,
        description: draft.description.trim(),
        occurred_on: draft.occurredOn,
        work_center_id: draft.workCenter?.value ?? '',
        respondent_kind: draft.respondentKind,
        respondent_text: draft.respondentText.trim(),
        identity_mode: 'anonymous',
        website: '',
    };
}
