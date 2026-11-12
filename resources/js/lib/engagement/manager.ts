import type { FeedbackItem } from '@/types';

/** A question about the manager as a row of the dumbbell: the group's share, the company's and the difference. */
export type DumbbellRow = {
    key: string;
    text: string;
    group: number;
    /** Null when the company has no figure to compare. */
    company: number | null;
    delta: number | null;
};

/**
 * The questions about one manager, the group beside the company, ordered by how far the group is from the company: what
 * stands out first, what lags last. It orders questions of one manager, never managers.
 */
export function dumbbellRows(items: FeedbackItem[]): DumbbellRow[] {
    return items
        .map((item) => ({
            key: item.key,
            text: item.text,
            group: item.favorable,
            company:
                item.vsCompany === null
                    ? null
                    : item.favorable - item.vsCompany,
            delta: item.vsCompany,
        }))
        .sort(
            (a, b) =>
                (b.delta ?? Number.NEGATIVE_INFINITY) -
                (a.delta ?? Number.NEGATIVE_INFINITY),
        );
}

/** The sentence over the feedback: what the group values most and where the opportunity is. */
export function managerReading(items: FeedbackItem[]): string | null {
    const rows = [...items].sort((a, b) => b.favorable - a.favorable);
    const best = rows[0];
    const worst = rows.at(-1);

    if (rows.length < 2 || best === undefined || worst === undefined) {
        return null;
    }

    return `Lo que su grupo más reconoce: «${best.text}». La oportunidad está en: «${worst.text}».`;
}
