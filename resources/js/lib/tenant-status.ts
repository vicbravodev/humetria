import type { TenantRow } from '@/types';

type BadgeVariant = 'default' | 'secondary' | 'outline' | 'destructive';

const VARIANTS: Record<TenantRow['status'], BadgeVariant> = {
    active: 'default',
    trial: 'secondary',
    not_started: 'outline',
    trial_ended: 'destructive',
    expired: 'destructive',
    deactivated: 'destructive',
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
    deactivated: REACTIVATE,
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
