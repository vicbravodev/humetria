import type { ClosedAnswers, Step } from '@/lib/engagement/survey-flow';

/** What the device keeps for a closed question the person chose to leave unanswered. */
export const SKIPPED = 'skip';

/** The closed answers kept on the device (strings, as `@/lib/survey` stores them) as numbers; skipped ones are left out. */
export function closedOf(stored: Record<string, string>): ClosedAnswers {
    const answers: ClosedAnswers = {};

    for (const [key, value] of Object.entries(stored)) {
        if (/^\d{1,2}$/.test(value)) {
            answers[key] = Number(value);
        }
    }

    return answers;
}

/**
 * Where a reload opens: at the first closed question not yet answered or skipped, or right after the last one seen when
 * none is left; the introduction when nothing was answered. Comments are never kept on the device, so open questions
 * do not count as seen.
 */
export function resumeIndex(
    list: Step[],
    stored: Record<string, string>,
): number {
    const closed = list.flatMap((step, index) =>
        step.kind === 'question' && step.item.scale !== 'open'
            ? [{ index, seen: stored[step.item.key] !== undefined }]
            : [],
    );
    const lastSeen = closed.filter((question) => question.seen).at(-1);

    if (lastSeen === undefined) {
        return 0;
    }

    return (
        closed.find((question) => !question.seen)?.index ?? lastSeen.index + 1
    );
}

/** The link's token in a survey address, "/engagement/{token}". */
export function tokenOfPath(path: string): string | null {
    const match = /^\/engagement\/([^/]+)$/.exec(path);

    return match?.[1] === undefined ? null : decodeURIComponent(match[1]);
}
