import { Activity, Calculator, Gauge, Network } from 'lucide-react';

import type { NavTenant } from '@/lib/app-navigation';
import { hasModule } from '@/lib/modules';
import { index as climate } from '@/routes/tenant/climate';
import { index as compensation } from '@/routes/tenant/compensation';
import { index as orgChart, quality } from '@/routes/tenant/org-chart';
import { index as pulse } from '@/routes/tenant/pulse';
import type { NavItem } from '@/types';

/**
 * The tools that are not compliance, for whoever may view them: the org chart, Clima and Pulso, in a company only (a union's
 * roster has no hierarchy) and only when the company has their module, and Compensación, in a union as well as in a company
 * (the server keeps them from anyone else).
 */
export const toolNavItems = (tenant: NavTenant | null): NavItem[] => {
    if (tenant === null) {
        return [];
    }

    const inCompany = tenant.type !== 'union';
    const tools: [module: string | null, visible: boolean, item: NavItem][] = [
        [
            'org_chart',
            tenant.can.view_org_chart && inCompany,
            {
                title: 'Organigrama',
                href: orgChart(tenant.slug),
                icon: Network,
                children: [
                    { title: 'Organigrama', href: orgChart(tenant.slug) },
                    { title: 'Calidad de datos', href: quality(tenant.slug) },
                ],
            },
        ],
        [
            'climate',
            tenant.can.view_climate && inCompany,
            { title: 'Clima', href: climate(tenant.slug), icon: Gauge },
        ],
        [
            'pulse',
            tenant.can.view_pulse && inCompany,
            { title: 'Pulso', href: pulse(tenant.slug), icon: Activity },
        ],
        [
            null,
            tenant.can.view_compensation,
            {
                title: 'Compensación',
                href: compensation(tenant.slug),
                icon: Calculator,
            },
        ],
    ];

    return tools
        .filter(
            ([module, visible]) =>
                visible && (module === null || hasModule(tenant, module)),
        )
        .map(([, , item]) => item);
};
