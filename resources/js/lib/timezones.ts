import type { TimezoneRules } from '@/types';

/** A municipality as the rules key it, as MexicanTimezones::normalize(): lower case, no accents, no dots, single spaces. */
function normalize(municipality: string): string {
    return municipality
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLowerCase()
        .replaceAll('.', '')
        .replace(/\s+/gu, ' ')
        .trim();
}

/**
 * The zone a work center gets by itself from its state and municipality, as the server's MexicanTimezones::suggest() gives
 * it, so the form shows it before saving.
 */
export function suggestTimezone(
    rules: TimezoneRules,
    state: string | undefined,
    municipality: string,
): string {
    if (state === undefined) {
        return rules.fallback;
    }

    return (
        rules.municipalities[state]?.[normalize(municipality)] ??
        rules.states[state] ??
        rules.fallback
    );
}
