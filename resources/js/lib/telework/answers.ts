import type { FavorableAnswer, PortalAnswer, PortalQuestion } from '@/types';

const LABELS: Record<PortalAnswer, string> = {
    yes: 'Sí',
    no: 'No',
    not_applicable: 'No aplica',
};

export function isAnswer(value: string): value is PortalAnswer {
    return Object.hasOwn(LABELS, value);
}

export function answerLabel(answer: PortalAnswer): string {
    return LABELS[answer];
}

/** The answer that flags a risk: the opposite of the favorable one. An informative question never flags. */
export function isUnfavorable(
    favorable: FavorableAnswer,
    answer: PortalAnswer | undefined,
): boolean {
    return (
        (favorable === 'yes' && answer === 'no') ||
        (favorable === 'no' && answer === 'yes')
    );
}

/** The key of the photos of the whole list, as the server names them. */
export const GENERAL = 'general';

export type ChecklistState = {
    consent: boolean | null;
    answers: Record<string, PortalAnswer>;
    notes: Record<string, string>;
    photos: Record<string, File[]>;
    specialNeeds: string;
};

/** The health questions are only asked of whoever accepted the consent. */
export function visibleQuestions(
    questions: PortalQuestion[],
    consent: boolean | null,
): PortalQuestion[] {
    return consent === true
        ? questions
        : questions.filter((question) => !question.sensitive);
}

export function groupByHeading(
    questions: PortalQuestion[],
): { heading: string; questions: PortalQuestion[] }[] {
    const groups = new Map<string, PortalQuestion[]>();

    for (const question of questions) {
        groups.set(question.heading, [
            ...(groups.get(question.heading) ?? []),
            question,
        ]);
    }

    return [...groups].map(([heading, grouped]) => ({
        heading,
        questions: grouped,
    }));
}

/** The visible questions that still lack an answer, or a risk note when the answer flags a risk. */
export function pendingQuestions(
    questions: PortalQuestion[],
    state: ChecklistState,
): string[] {
    return visibleQuestions(questions, state.consent)
        .filter((question) => {
            const answer = state.answers[question.id];

            return (
                answer === undefined ||
                (isUnfavorable(question.favorable, answer) &&
                    (state.notes[question.id] ?? '').trim() === '')
            );
        })
        .map((question) => question.id);
}

/** Ready to send: the consent is decided (either way) and nothing is pending. */
export function isComplete(
    questions: PortalQuestion[],
    state: ChecklistState,
): boolean {
    return (
        state.consent !== null &&
        pendingQuestions(questions, state).length === 0
    );
}

/**
 * What goes to the server: only what the person can still see. A note or photo of an answer they changed back, a health
 * answer after declining the consent, would be refused there.
 */
export function submission(questions: PortalQuestion[], state: ChecklistState) {
    const consent = state.consent === true;
    const shown = visibleQuestions(questions, state.consent);
    const flagged = shown.filter((question) =>
        isUnfavorable(question.favorable, state.answers[question.id]),
    );

    return {
        consent,
        answers: Object.fromEntries(
            shown.flatMap((question) => {
                const answer = state.answers[question.id];

                return answer === undefined ? [] : [[question.id, answer]];
            }),
        ),
        risk_notes: Object.fromEntries(
            flagged.map((question) => [
                question.id,
                (state.notes[question.id] ?? '').trim(),
            ]),
        ),
        photos: consent
            ? Object.fromEntries(
                  [GENERAL, ...flagged.map((question) => question.id)].flatMap(
                      (key) => {
                          const files = state.photos[key] ?? [];

                          return files.length === 0 ? [] : [[key, files]];
                      },
                  ),
              )
            : {},
        special_needs: consent ? state.specialNeeds.trim() : '',
    };
}

/**
 * Each photo in its own flat field, `photo_{question}_{n}`: the server reads these the same way everywhere, which a
 * nested name (`photos[id][0]`) is not.
 */
export function photoFields(
    photos: Record<string, File[]>,
): Record<string, File> {
    return Object.fromEntries(
        Object.entries(photos).flatMap(([question, files]) =>
            files.map((file, index) => [`photo_${question}_${index}`, file]),
        ),
    );
}
