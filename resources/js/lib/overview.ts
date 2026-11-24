import type { WhatsAppMonth } from '@/types';

const PESOS = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    maximumFractionDigits: 0,
});

/** Cents as the staff reads them: "$24,900". */
export function pesos(cents: number): string {
    return PESOS.format(cents / 100);
}

/** What the card of the demo requests says: how many wait for someone. */
export function demosLine(waiting: number): string {
    return waiting === 0
        ? 'Ninguna espera respuesta.'
        : `${waiting} ${waiting === 1 ? 'espera' : 'esperan'} respuesta.`;
}

/** What the card of the quiet companies says. */
export function quietLine(quiet: number): string {
    return quiet === 0
        ? 'Todas tuvieron un acceso en el último mes.'
        : `${quiet} ${quiet === 1 ? 'empresa no tiene' : 'empresas no tienen'} un acceso en 30 días: vale la pena escribirles.`;
}

const PESOS_AND_CENTS = new Intl.NumberFormat('es-MX', {
    style: 'currency',
    currency: 'MXN',
    minimumFractionDigits: 2,
});

/** Cents with their cents, for the small amounts of WhatsApp: "$0.68". */
export function pesosAndCents(cents: number): string {
    return PESOS_AND_CENTS.format(cents / 100);
}

/** What the card of WhatsApp says: Meta's cost, what the companies were charged and what stays. */
export function whatsAppLine(whatsApp: WhatsAppMonth): string {
    return whatsApp.messages === 0
        ? 'Todavía no sale ningún mensaje este mes.'
        : `Costo de Meta ${pesosAndCents(whatsApp.costCents)} · cobrado ${pesosAndCents(whatsApp.chargedCents)} · margen ${pesosAndCents(whatsApp.marginCents)}.`;
}
