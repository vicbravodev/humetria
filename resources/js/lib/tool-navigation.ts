import { Calculator, Network } from 'lucide-react';

import type { NavTenant } from '@/lib/app-navigation';
import { index as compensation } from '@/routes/tenant/compensation';
import { index as orgChart } from '@/routes/tenant/org-chart';
import type { NavItem } from '@/types';

/**
 * The tools that are not compliance, for whoever may view them: the org chart, in a company only (a union's roster
 * has no hierarchy), and Compensación, in a union as well as in a company (the server keeps them from anyone else).
 */
export const toolNavItems = (tenant: NavTenant | null): NavItem[] =>
    tenant === null
        ? []
        : [
              ...(tenant.can.view_org_chart && tenant.type !== 'union'
                  ? [
                        {
                            title: 'Organigrama',
                            href: orgChart(tenant.slug),
                            icon: Network,
                        },
                    ]
                  : []),
              ...(tenant.can.view_compensation
                  ? [
                        {
                            title: 'Compensación',
                            href: compensation(tenant.slug),
                            icon: Calculator,
                        },
                    ]
                  : []),
          ];
