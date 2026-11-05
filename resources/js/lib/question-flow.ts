/**
 * A document written with questions (decisión A3; boards V2-Politica and V3-Doc-Riesgos): its questions, the answers by
 * field, the sections their text makes, and what the flow works out from them.
 */
export type FlowOption = { value: string; label: string };

export type FlowField = {
    key: string;
    legend: string;
    /** Whether the legend is shown, or only read aloud (a question with a single field). */
    shown: boolean;
    multiple: boolean;
    columns: number;
    options: FlowOption[];
};

export type FlowQuestion = {
    key: string;
    topic: string;
    title: string;
    hint: string | null;
    fields: FlowField[];
};

export type FlowAnswer = string | string[] | null;

export type FlowAnswers = Record<string, FlowAnswer>;

export type FlowSection = {
    key: string;
    heading: string;
    /** Null while its question is not answered. */
    text: string | null;
    /** A base text that needs no question. */
    base: boolean;
    /** RH rewrote it. */
    edited: boolean;
};

export type FlowTopic = { key: string; label: string };

export type TopicState = 'done' | 'base' | 'open';

/** About how many minutes are left on a question: the document's minutes spread over its questions, at least one. */
export function minutesLeft(
    total: number,
    count: number,
    index: number,
): number {
    return Math.max(1, Math.round((total * (count - index)) / count));
}

/** A list of choices with `value` added, or taken off when it is there. */
export function toggled(values: string[], value: string): string[] {
    return values.includes(value)
        ? values.filter((each) => each !== value)
        : [...values, value];
}

/** The answers given so far: a single choice still open is left out, so only real answers reach the server. */
export function answered(
    answers: FlowAnswers,
): Record<string, string | string[]> {
    const given: Record<string, string | string[]> = {};

    for (const [field, answer] of Object.entries(answers)) {
        if (answer !== null) {
            given[field] = answer;
        }
    }

    return given;
}

/** The questions with a choice still open: a single one without answer, or a list with nothing chosen. */
export function unanswered(
    questions: FlowQuestion[],
    answers: FlowAnswers,
): FlowQuestion[] {
    return questions.filter((question) =>
        question.fields.some((field) => {
            const answer = answers[field.key] ?? null;

            return answer === null || answer.length === 0;
        }),
    );
}

/**
 * Each topic of the norm (the incisos of 5.2) with how it stands: done once its section has text, a base text apart until
 * the review (board V2-Politica: «Los punteados ya tienen texto base»), open otherwise.
 */
export function topicStates(
    topics: FlowTopic[],
    sections: FlowSection[],
    reviewing: boolean,
): (FlowTopic & { state: TopicState })[] {
    return topics.map((topic) => {
        const section = sections.find(({ key }) => key === topic.key);
        let state: TopicState = 'open';

        if (section !== undefined && section.text !== null) {
            state = section.base && !reviewing ? 'base' : 'done';
        }

        return { ...topic, state };
    });
}

/**
 * The sections as far as the questions went (board V2-Politica): the text of the questions up to `index`, and the base
 * texts; the sections of the questions ahead wait as grey lines, even when the board marked their answer.
 */
export function reached(
    sections: FlowSection[],
    questions: FlowQuestion[],
    index: number,
): FlowSection[] {
    const ahead = new Set(questions.slice(index + 1).map(({ key }) => key));

    return sections.map((section) =>
        ahead.has(section.key) ? { ...section, text: null } : section,
    );
}

/** The paragraphs of a text, split on its blank lines. */
export function paragraphs(text: string): string[] {
    return text
        .split(/\n\s*\n/)
        .map((paragraph) => paragraph.trim())
        .filter((paragraph) => paragraph !== '');
}
