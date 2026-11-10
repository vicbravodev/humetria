import { countOf } from '@/lib/plural';
import type { BadgeVariant, TenantFilters, TenantRow } from '@/types';

const VARIANTS: Record<TenantRow['status'], BadgeVariant> = {
    active: 'default',
    trial: 'default',
    not_started: 'outline',
    trial_ended: 'destructive',
    expired: 'destructive',
    read_only: 'secondary',
    suspended: 'destructive',
    archived: 'outline',
};

export function tenantStatusVariant(status: TenantRow['status']): BadgeVariant {
    return VARIANTS[status];
}

type ActivationCopy = { action: string; title: string; submit: string };

const ACTIVATE: ActivationCopy = {
    action: 'Activar',
    title: 'Activar empresa',
    submit: 'Activar',
};
const REACTIVATE: ActivationCopy = {
    action: 'Reactivar',
    title: 'Reactivar empresa',
    submit: 'Reactivar',
};

// An active company only moves its end date; a blocked one comes back.
const ACTIVATION: Record<TenantRow['status'], ActivationCopy> = {
    not_started: ACTIVATE,
    trial: ACTIVATE,
    active: {
        action: 'Cambiar vigencia',
        title: 'Cambiar vigencia',
        submit: 'Guardar',
    },
    trial_ended: REACTIVATE,
    expired: REACTIVATE,
    read_only: REACTIVATE,
    suspended: REACTIVATE,
    archived: REACTIVATE,
};

export function activationCopy(status: TenantRow['status']): ActivationCopy {
    return ACTIVATION[status];
}

/** What the console offers about the company's owner: invite the first one, or replace the one it has. */
export function ownerAction(owner: TenantRow['owner']): string {
    return owner === null ? 'Invitar owner' : 'Cambiar owner';
}

/** The company switcher's note for a company in trial. */
export function trialLabel(daysLeft: number): string {
    return daysLeft === 1
        ? 'Prueba · último día'
        : `Prueba · quedan ${daysLeft} días`;
}

/** The console's line under "Empresas": how many there are and, when any, how many trials end this week. */
export function companiesSummary(total: number, endingSoon: number): string {
    const companies = countOf(total, 'empresa', 'empresas');

    if (endingSoon === 0) {
        return companies;
    }

    return endingSoon === 1
        ? `${companies} · 1 prueba termina esta semana`
        : `${companies} · ${endingSoon} pruebas terminan esta semana`;
}

/** The console's search and view as its address carries them: without the defaults. */
export function companiesQuery(
    filters: TenantFilters,
): Partial<Record<keyof TenantFilters, string>> {
    const q = filters.q.trim();

    return {
        ...(q === '' ? {} : { q }),
        ...(filters.status === 'all' ? {} : { status: filters.status }),
    };
}
