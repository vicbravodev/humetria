import { shortDate } from '@/lib/leave/format';
import { countOf } from '@/lib/plural';
import type { LeaveRecordPeriod, LeaveRecordStatement } from '@/types';

/** "+3 días", "−1 día": the days of a movement with their sign, the minus a real one. */
export function entryDays(days: number): string {
    const sign = days < 0 ? '−' : '+';

    return `${sign}${countOf(Math.abs(days), 'día', 'días')}`;
}

/** What RH reads under the name of a person who left: since when, and what they are owed in days. */
export function owedDays(pending: number, proportional: number): number {
    return Math.round((pending + proportional) * 100) / 100;
}

/** The period as three dates, in order: when it was given and how many days, to enjoy it before, and when it prescribes. */
export function milestones(
    period: LeaveRecordPeriod,
): { title: string; detail: string }[] {
    return [
        {
            title: 'Otorgado',
            detail: `${shortDate(period.anniversaryOn)} · ${countOf(period.granted, 'día', 'días')}`,
        },
        {
            title: 'A disfrutar antes del',
            detail: `${shortDate(period.enjoyBy)}${period.overdue ? ' · Vencido' : ''}`,
        },
        { title: 'Prescribe el', detail: shortDate(period.prescribesOn) },
    ];
}

/** The newest statement the person has not confirmed, to offer sending it again; the server sends them newest first. */
export function openStatement(
    statements: LeaveRecordStatement[],
): LeaveRecordStatement | null {
    return (
        statements.find((statement) => statement.acknowledgedOn === null) ??
        null
    );
}

/** What each tab of the record holds: the paged ones by their totals, the short ones by their length. */
export function tabCounts(held: {
    movements: number;
    requests: number;
    statements: { acknowledgedOn: string | null }[];
    periods: number;
}): Record<'movements' | 'requests' | 'statements' | 'periods', number> {
    return { ...held, statements: held.statements.length };
}
