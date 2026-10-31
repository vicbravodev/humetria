import { describeDate } from '@/lib/calendar-date';
import type { Option } from '@/lib/option-search';
import type {
    EndReasonValue,
    TeleworkerStatus,
    WorkplaceStatusValue,
} from '@/types';

/** The estado civil of numeral 5.1, in the order RH reads them. */
export const MARITAL_STATUSES: Option[] = [
    { value: 'single', label: 'Soltero(a)' },
    { value: 'married', label: 'Casado(a)' },
    { value: 'free_union', label: 'Unión libre' },
    { value: 'separated', label: 'Separado(a)' },
    { value: 'divorced', label: 'Divorciado(a)' },
    { value: 'widowed', label: 'Viudo(a)' },
];

/** Why a telework period ends, with what each one means for RH. */
export const END_REASONS: { value: EndReasonValue; label: string }[] = [
    { value: 'reversibility', label: 'Reversibilidad' },
    { value: 'left_company', label: 'Salió de la empresa' },
    { value: 'transfer', label: 'Cambio de centro de trabajo' },
    { value: 'other', label: 'Otro motivo' },
];

/** Until when a validation holds, or that it lapsed (derived: a lapsed one is pending again). */
export function expiryText(
    status: WorkplaceStatusValue,
    expiresOn: string | null,
): string | null {
    const day = expiresOn === null ? undefined : describeDate(expiresOn);

    if (day === undefined || status === 'retired') {
        return null;
    }

    return status === 'pending' ? `Venció el ${day}` : `Vence el ${day}`;
}

/** What the roster panel says under its title. */
export function rosterHint(
    status: TeleworkerStatus,
    canJoin: boolean,
    since: string | undefined,
): string {
    if (status === 'active') {
        return since === undefined ? 'En el padrón.' : `Desde el ${since}.`;
    }

    return canJoin
        ? 'Tiene un lugar validado y vigente: ya puedes pasarla al padrón.'
        : 'Necesita al menos un lugar validado y vigente.';
}
