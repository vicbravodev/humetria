import { usePage } from '@inertiajs/react';
import type { NavTenant } from '@/types';

/**
 * The company as the menu sees it: with what the role allows, not what the company's state leaves to change. A read-only
 * company has its writing permissions off, but it still reads and exports from the same pages.
 */
export function useNavTenant(): NavTenant | null {
    const { tenant } = usePage().props;

    return tenant === null ? null : { ...tenant, can: tenant.permissions };
}
