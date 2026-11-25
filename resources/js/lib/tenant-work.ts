import { countOf } from '@/lib/plural';
import type { TenantRow } from '@/types';

/** The seats a company uses of its plan ("96 de 150"); a company with no plan has no limit. */
export function seatsText(seats: TenantRow['seats']): string {
    return seats === null ? 'Sin plan' : `${seats.used} de ${seats.amount}`;
}

/** The title of the dialog that gives more days of trial to the chosen companies. */
export function trialExtensionCopy(count: number): string {
    return `Extender la prueba de ${countOf(count, 'empresa', 'empresas')}`;
}
