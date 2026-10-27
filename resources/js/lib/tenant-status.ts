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
