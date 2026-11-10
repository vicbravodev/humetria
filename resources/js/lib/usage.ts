import type { UsageSummary } from '@/types';

/** 250000 as "250,000". */
export const formatTokens = (tokens: number): string =>
    tokens.toLocaleString('es-MX');

/** How much of the month's quota is used or held, from 0 to 100; a quota of zero is all of it. */
export function usagePercent(
    usage: Pick<UsageSummary, 'used' | 'reserved' | 'quota'>,
): number {
    if (usage.quota === 0) {
        return 100;
    }

    return Math.min(
        100,
        Math.round(((usage.used + usage.reserved) / usage.quota) * 100),
    );
}

/** What is left of the month, in a sentence. */
export function usageLine(usage: UsageSummary): string {
    if (usage.remaining === 0) {
        return 'Sin tokens: Humi no responde hasta el mes siguiente, o hasta agregar más.';
    }

    const left = `Quedan ${formatTokens(usage.remaining)} tokens`;

    return usage.reserved > 0
        ? `${left}; ${formatTokens(usage.reserved)} están apartados para respuestas en curso.`
        : `${left}.`;
}
