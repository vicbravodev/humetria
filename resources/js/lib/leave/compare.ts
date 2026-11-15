import { percentOf } from '@/lib/chart';
import { countOf } from '@/lib/plural';
import type { LeaveAssignments, LeavePolicyOption } from '@/types';

/** «+2 días» when a policy gives more days than the law; nothing when it only equals it (it can never give less). */
export function daysDelta(days: number, law: number): string | null {
    return days > law ? `+${countOf(days - law, 'día', 'días')}` : null;
}

/** «+5 pts» when a policy pays a higher premium than the law; nothing when it only equals it. */
export function premiumDelta(percent: number, law: number): string | null {
    return percent > law ? `+${percent - law} pts` : null;
}

/** A column of the chart of days by seniority: from zero, against the tallest of the comparison, with the law as its mark. */
export type YearColumn = {
    key: string;
    label: string;
    valueText: string;
    tooltip: string;
    percent: number;
    referencePercent: number;
};

/**
 * The days a policy gives in years 1 to 6 (the sixth stands for 6 to 10) as columns on the scale `top` shares with the other
 * policies, each with the days the law gives for the year as a mark across it.
 */
export function yearColumns(
    byYear: number[],
    lawByYear: number[],
    top: number,
): YearColumn[] {
    return byYear.map((days, index) => {
        const law = lawByYear[index] ?? 0;
        const last = index === byYear.length - 1 && byYear.length > 1;
        const years = last
            ? `Años ${index + 1} a ${index + 5}`
            : `Año ${index + 1}`;

        return {
            key: `year-${index + 1}`,
            label: last ? `${index + 1}–${index + 5}` : String(index + 1),
            valueText: String(days),
            tooltip: `${years}: ${countOf(days, 'día', 'días')}; la ley da ${law}`,
            percent: percentOf(days, top),
            referencePercent: percentOf(law, top),
        };
    });
}

const GROUPS: { key: keyof LeaveAssignments; label: string; verb: string }[] = [
    { key: 'default', label: 'Toda la empresa', verb: 'sigue' },
    { key: 'unionized', label: 'Sindicalizados', verb: 'siguen' },
    { key: 'trusted', label: 'Personal de confianza', verb: 'sigue' },
];

/** «Sindicalizados siguen Contrato colectivo»: whom each policy goes to, or that the law follows (also for a policy now archived). */
export function assignmentSentences(
    assignments: LeaveAssignments,
    options: LeavePolicyOption[],
): { key: keyof LeaveAssignments; label: string; text: string }[] {
    return GROUPS.map(({ key, label, verb }) => {
        const policy = options.find(
            (option) => option.value === assignments[key],
        );

        return {
            key,
            label,
            text: `${label} ${verb} ${policy === undefined ? 'la Ley (sin política)' : policy.label}`,
        };
    });
}

const plain = (text: string): string =>
    text
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase()
        .trim();

/** The group that follows the law and is named like a policy of the company, which suggests the policy was meant for it. */
export function nameClash(
    assignments: LeaveAssignments,
    options: LeavePolicyOption[],
): string | null {
    const group = GROUPS.filter(({ key }) => key !== 'default').find(
        ({ key, label }) =>
            assignments[key] === null &&
            options.some((option) => plain(option.label) === plain(label)),
    );

    return group?.label ?? null;
}

/** The staff groups that follow a policy, by the name the page gives them. */
export function appliesTo(
    policyId: string,
    assignments: LeaveAssignments,
): string[] {
    return GROUPS.filter(({ key }) => assignments[key] === policyId).map(
        ({ label }) => label,
    );
}
