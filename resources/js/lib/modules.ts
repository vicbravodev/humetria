import type { NavTenant } from '@/types';

/** Whether the company has the module; a page that did not share the list (outside a company) offers everything. */
export const hasModule = (tenant: NavTenant, module: string): boolean =>
    tenant.modules === undefined || tenant.modules.includes(module);
