import { describeDate } from '@/lib/calendar-date';
import { plural } from '@/lib/plural';
import type { CenterRequirement } from '@/types';

/** What is behind a requirement that is not met: "3 pendientes" for what is missing, "1 aviso" for a warning, nothing when met. */
export function requirementCount(
    requirement: CenterRequirement,
): string | null {
    const { state, count } = requirement;

    if (state === 'met' || state === 'pending' || count === 0) {
        return null;
    }

    return state === 'missing'
        ? `${count} ${plural(count, 'pendiente', 'pendientes')}`
        : `${count} ${plural(count, 'aviso', 'avisos')}`;
}

/** "Desde el 2 de octubre de 2026" for the oldest thing waiting; nothing when there is no date. */
export function requirementSince(
    requirement: CenterRequirement,
): string | null {
    const day =
        requirement.since === null
            ? undefined
            : describeDate(requirement.since);

    return day === undefined ? null : `Desde el ${day}`;
}

/** The sentence over the board: how many of the thirteen are met, and how many need a look or are missing. */
export function summaryText(summary: {
    met: number;
    warning: number;
    missing: number;
    pending: number;
}): string {
    const total =
        summary.met + summary.warning + summary.missing + summary.pending;
    const rest = [
        summary.warning > 0
            ? `${summary.warning} con ${plural(summary.warning, 'aviso', 'avisos')}`
            : null,
        summary.missing > 0 ? `${summary.missing} por resolver` : null,
        summary.pending > 0 ? `${summary.pending} sin registros` : null,
    ].filter((part): part is string => part !== null);

    return [`${summary.met} de ${total} cumplen`, ...rest].join(' · ');
}
