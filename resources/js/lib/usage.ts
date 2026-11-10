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

const BYTE_UNITS = ['B', 'KB', 'MB', 'GB', 'TB'];

/** 1572864 bytes as "1.5 MB"; 1200 correos as "1,200 correos". */
export function formatAmount(amount: number, unit: string): string {
    if (unit !== 'bytes') {
        return `${formatTokens(amount)} ${unit}`;
    }

    let value = amount;
    let step = 0;

    while (value >= 1024 && step < BYTE_UNITS.length - 1) {
        value /= 1024;
        step += 1;
    }

    return `${Number(value.toFixed(1)).toLocaleString('es-MX')} ${BYTE_UNITS[step]}`;
}
