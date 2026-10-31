import type { Answers, Step } from '@/lib/survey';

/**
 * Where a person picks a guide up again: the first question still unanswered, or the last screen's when all are. A
 * question, not a screen number, so the place survives a regrouping of the screens.
 */
export function resumeKey(
    screens: Step[],
    answers: Answers,
): string | undefined {
    const pending = screens
        .flatMap((screen) => screen.keys)
        .find((key) => answers[key] === undefined);

    return pending ?? screens.at(-1)?.keys[0];
}

/**
 * The screen that holds a question: a phone shows one per screen, a computer up to three. The last screen when the
 * question is no longer asked (a "no" made the guide shorter).
 */
export function stepHolding(screens: Step[], key: string | undefined): number {
    const index = screens.findIndex(
        (screen) => key !== undefined && screen.keys.includes(key),
    );

    return index === -1 ? screens.length - 1 : index;
}
