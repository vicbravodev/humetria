import type { PageContext } from '@/lib/assistant/chat-request';

const GENERAL = [
    '¿Qué me falta para estar al día?',
    '¿Cómo va la participación?',
    '¿Qué pendientes tengo primero?',
    'Explícame la NOM-037',
] as const;

const CAMPAIGN = [
    '¿Cómo va la participación?',
    '¿Dónde está el riesgo más alto?',
    '¿Qué me falta para cerrarla?',
] as const;

const WORK_CENTER = [
    '¿Cómo va este centro con la NOM-035?',
    '¿Qué le falta a este centro en la NOM-037?',
    '¿Qué pendientes tiene este centro?',
] as const;

/** What to offer as a first question, by the page the person has open. */
export function suggestionsFor(page: PageContext): readonly string[] {
    switch (page?.kind) {
        case 'campaign':
            return CAMPAIGN;
        case 'work_center':
            return WORK_CENTER;
        default:
            return GENERAL;
    }
}

const FOLLOW_UPS: Record<string, readonly string[]> = {
    progress: ['¿Cuándo cierra?', '¿Dónde está el riesgo más alto?'],
    risk_levels: [
        '¿Qué dice la norma que debo hacer?',
        '¿Qué pendientes tengo primero?',
    ],
    status_list: [
        '¿Qué pendientes tengo primero?',
        '¿Cómo va la participación?',
    ],
    list: [],
};

/** Two or three questions to follow an answer, by the cards it carried. */
export function followUpsFor(renders: readonly string[]): readonly string[] {
    const last = renders.at(-1);

    return last === undefined ? [] : (FOLLOW_UPS[last] ?? []);
}

/** A first question, with the live fact that makes it worth asking when there is one («142 de 186 respondieron»). */
export type Starter = { text: string; live: string | null };

/** The first questions with their live facts: a question with no fact (or an empty one) shows alone, never a made-up one. */
export function startersOf(
    questions: readonly string[],
    live: Readonly<Record<string, string>>,
): Starter[] {
    return questions.map((text) => {
        const fact = live[text]?.trim() ?? '';

        return { text, live: fact === '' ? null : fact };
    });
}

/** Which fact of the server answers each first question: a question with no fact of its own shows alone. */
const FACT_OF_QUESTION: Readonly<Record<string, string>> = {
    '¿Qué me falta para estar al día?': 'pending',
    '¿Cómo va la participación?': 'participation',
    '¿Dónde está el riesgo más alto?': 'risk',
    'Explícame la NOM-037': 'telework',
};

/** The live facts of the company («3 pendientes…») by the first question each one answers. */
export function liveFactsByQuestion(
    facts: Readonly<Record<string, string>>,
): Record<string, string> {
    return Object.fromEntries(
        Object.entries(FACT_OF_QUESTION).flatMap(([question, key]) => {
            const fact = facts[key];

            return fact === undefined ? [] : [[question, fact]];
        }),
    );
}

/**
 * The first questions for the page open, with the live facts of the company under the general ones: under the questions
 * of one campaign or one center a fact of the whole company would not be exact, so those show alone.
 */
export function startersFor(
    page: PageContext,
    facts: Readonly<Record<string, string>>,
): Starter[] {
    return startersOf(
        suggestionsFor(page),
        page === null ? liveFactsByQuestion(facts) : {},
    );
}
