export type SurveyScale = 'agreement' | 'recommend' | 'open';

export type SurveyQuestion = {
    key: string;
    text: string;
    scale: SurveyScale;
};

export type SurveySection = {
    block: string;
    title: string;
    instruction: string;
    items: SurveyQuestion[];
};

export type Step =
    | { kind: 'intro' }
    | { kind: 'section'; section: number; title: string; instruction: string }
    | { kind: 'question'; section: number; item: SurveyQuestion }
    | { kind: 'submit' };

/** A closed answer is a whole number; null is a question the person skipped. */
export type ClosedAnswers = Record<string, number | undefined>;

export type Replies = {
    answers: ClosedAnswers;
    comments: Record<string, string | undefined>;
};

export type SurveyPayload = {
    answers: Record<string, number | null>;
    comments: Record<string, string>;
    minutes: number;
};

export type Progress = { position: number; total: number; fraction: number };

const MINUTE = 60_000;

/** The screens of the survey: introduction, each section's instruction (if it has one), one question per step, send. */
export function steps(sections: SurveySection[]): Step[] {
    const list: Step[] = [{ kind: 'intro' }];

    sections.forEach((section, index) => {
        if (section.instruction !== '') {
            list.push({
                kind: 'section',
                section: index,
                title: section.title,
                instruction: section.instruction,
            });
        }

        for (const item of section.items) {
            list.push({ kind: 'question', section: index, item });
        }
    });

    list.push({ kind: 'submit' });

    return list;
}

/**
 * What is sent: every closed question shown (null when skipped), the comments with text (trimmed, otherwise literal)
 * and the minutes it took, between 1 and 60. Anything not shown never goes.
 */
export function payload(
    sections: SurveySection[],
    replies: Replies,
    clock: { startedAt: number; now: number },
): SurveyPayload {
    const { answers, comments } = replies;
    const { startedAt, now } = clock;
    const sent: SurveyPayload = {
        answers: {},
        comments: {},
        minutes: Math.min(
            60,
            Math.max(1, Math.round((now - startedAt) / MINUTE)),
        ),
    };

    for (const item of sections.flatMap((section) => section.items)) {
        if (item.scale !== 'open') {
            sent.answers[item.key] = answers[item.key] ?? null;
            continue;
        }

        const text = (comments[item.key] ?? '').trim();

        if (text !== '') {
            sent.comments[item.key] = text;
        }
    }

    return sent;
}

/** How many questions are behind the current step, out of all of them. The introduction is 0 and the send step is full. */
export function progressOf(list: Step[], index: number): Progress {
    const total = list.filter((step) => step.kind === 'question').length;
    const position = list
        .slice(0, index + 1)
        .filter((step) => step.kind === 'question').length;
    const done = list[index]?.kind === 'submit' ? total : position;

    return { position: done, total, fraction: total === 0 ? 0 : done / total };
}

/** What the main button says: the last question leads to the send step, which sends. Nothing moves on by itself. */
export function nextLabel(
    list: Step[],
    index: number,
): 'Siguiente' | 'Revisar y enviar' | 'Enviar mis respuestas' {
    if (list[index]?.kind === 'submit') {
        return 'Enviar mis respuestas';
    }

    return list[index + 1]?.kind === 'submit' &&
        list[index]?.kind === 'question'
        ? 'Revisar y enviar'
        : 'Siguiente';
}

export type Tally = {
    answered: number;
    total: number;
    /** The step of the first question left blank, to go back to it; none when all were answered. */
    firstOmitted: number | null;
};

/**
 * How many questions were answered before sending: a closed one with its answer, an open one with text. Only counts:
 * the send step shows numbers, never an answer.
 */
export function tallyOf(list: Step[], replies: Replies): Tally {
    const tally: Tally = { answered: 0, total: 0, firstOmitted: null };

    list.forEach((step, index) => {
        if (step.kind !== 'question') {
            return;
        }

        const { key, scale } = step.item;
        const answered =
            scale === 'open'
                ? (replies.comments[key] ?? '').trim() !== ''
                : replies.answers[key] !== undefined;

        tally.total += 1;
        tally.answered += answered ? 1 : 0;
        tally.firstOmitted ??= answered ? null : index;
    });

    return tally;
}
