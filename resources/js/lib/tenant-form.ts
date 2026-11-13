import { slugify } from '@/lib/slug';
import type { TenantPrefill, TenantRow } from '@/types';

/** What the company form starts with: the company being edited, or the demo request it is created from, or nothing. */
export function tenantDefaults(
    tenant: Pick<TenantRow, 'legal_name' | 'trade_name' | 'slug'> | undefined,
    prefill: TenantPrefill | null | undefined,
): { legalName: string; tradeName: string; slug: string } {
    if (tenant !== undefined) {
        return {
            legalName: tenant.legal_name,
            tradeName: tenant.trade_name,
            slug: tenant.slug,
        };
    }

    const company = prefill?.company ?? '';

    return { legalName: company, tradeName: company, slug: slugify(company) };
}
