import { countOf } from '@/lib/plural';

/** "+3 días", "−1 día": the days of a movement with their sign, the minus a real one. */
export function entryDays(days: number): string {
    const sign = days < 0 ? '−' : '+';

    return `${sign}${countOf(Math.abs(days), 'día', 'días')}`;
}

/** What RH reads under the name of a person who left: since when, and what they are owed in days. */
export function owedDays(pending: number, proportional: number): number {
    return Math.round((pending + proportional) * 100) / 100;
}
